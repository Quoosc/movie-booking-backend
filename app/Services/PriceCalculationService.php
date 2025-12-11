<?php
//app/Services/PriceCalculationService.php
namespace App\Services;

use App\Models\MembershipTier;
use App\Models\PriceBase;
use App\Models\PriceModifier;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\ValueObjects\DiscountResult;



class PriceCalculationService
{
    /**
     * Tính giá base + breakdown (KHÔNG áp ticket type).
     * Trả về mảng: [0] = finalPrice (BigDecimal), [1] = breakdown JSON.
     */
    public function calculatePriceWithBreakdown(Showtime $showtime, Seat $seat): array
    {
        // Lấy base price đang active
        $priceBase = PriceBase::where('is_active', true)
            ->orderByDesc('created_at')
            ->first();

        if (!$priceBase) {
            throw new \RuntimeException('No active base price configured');
        }

        $basePriceValue = (float) $priceBase->basePrice;
        $finalPrice     = $basePriceValue;

        Log::debug('Starting price calculation. Base price: {price}', ['price' => $finalPrice]);

        // Breakdown: basePrice + modifiers + finalPrice
        $breakdown = [
            'basePrice' => $basePriceValue,
            'modifiers' => [],
            'finalPrice' => null,
        ];

        // Lấy toàn bộ modifiers đang active
        $modifiers = PriceModifier::where('is_active', true)->get();

        $applicable = [];
        foreach ($modifiers as $modifier) {
            if ($this->isModifierApplicable($modifier, $showtime, $seat)) {
                $applicable[] = $modifier;
                Log::debug(
                    'Applicable modifier: {name} - {type} = {value}',
                    [
                        'name'  => $modifier->name,
                        'type'  => $modifier->condition_type,
                        'value' => $modifier->condition_value,
                    ]
                );
            }
        }

        // Áp các modifier
        foreach ($applicable as $modifier) {
            $before      = $finalPrice;
            $finalPrice  = $this->applyModifier($finalPrice, $modifier);
            $change      = $finalPrice - $before;

            $breakdown['modifiers'][] = [
                'name'  => $modifier->name,
                'type'  => $modifier->condition_type . ':' . $modifier->condition_value,
                'value' => round($change, 2),
            ];

            Log::debug('After applying {name}: {price}', [
                'name'  => $modifier->name,
                'price' => $finalPrice,
            ]);
        }

        // Round 2 decimals
        $finalPrice = round($finalPrice, 2);
        $breakdown['finalPrice'] = $finalPrice;

        // JSON breakdown
        $breakdownJson = json_encode($breakdown, JSON_UNESCAPED_UNICODE);
        if ($breakdownJson === false) {
            Log::error('Failed to serialize price breakdown');
            $breakdownJson = '{}';
        }

        Log::info(
            'Final calculated base price for showtime {showtime} seat {row}{num}: {price}',
            [
                'showtime' => $showtime->showtime_id ?? $showtime->id ?? null,
                'row'      => $seat->row_label ?? '',
                'num'      => $seat->seat_number ?? '',
                'price'    => $finalPrice,
            ]
        );

        return [$finalPrice, $breakdownJson];
    }

    /**
     * Chỉ trả về base price (dùng cho TicketTypeService).
     */
    public function calculatePrice(Showtime $showtime, Seat $seat)
    {
        [$price] = $this->calculatePriceWithBreakdown($showtime, $seat);
        return $price;
    }

    /**
     * Check một modifier có áp dụng không.
     */
    private function isModifierApplicable(PriceModifier $modifier, Showtime $showtime, Seat $seat): bool
    {
        $conditionType  = strtoupper($modifier->condition_type);
        $conditionValue = $modifier->condition_value;

        switch ($conditionType) {
            case 'DAY_TYPE':
                return $this->checkDayType($conditionValue, $showtime->start_time);

            case 'TIME_RANGE':
                return $this->checkTimeRange($conditionValue, $showtime->start_time);

            case 'FORMAT':
                return $this->checkFormat($conditionValue, $showtime->format);

            case 'ROOM_TYPE':
                $roomType = $showtime->room ? $showtime->room->room_type ?? null : null;
                return $this->checkRoomType($conditionValue, $roomType);

            case 'SEAT_TYPE':
                $seatType = $seat->seat_type ?? null;
                return $this->checkSeatType($conditionValue, $seatType);

            default:
                return false;
        }
    }

    /**
     * Áp modifier lên current price.
     * PERCENTAGE: price * (1 + value / 100)
     * FIXED_AMOUNT: price + value
     */
    private function applyModifier(float $currentPrice, PriceModifier $modifier): float
    {
        $type  = strtoupper($modifier->modifier_type);
        $value = (float) $modifier->modifier_value;

        if ($type === 'PERCENTAGE') {
            $multiplier = 1 + $value / 100.0;
            return $currentPrice * $multiplier;
        }

        // FIXED_AMOUNT hoặc default
        return $currentPrice + $value;
    }

    /**
     * WEEKEND / WEEKDAY.
     */
    private function checkDayType(string $conditionValue, $startTime): bool
    {
        $dt = $startTime instanceof Carbon ? $startTime : Carbon::parse($startTime);
        $dayOfWeek = $dt->dayOfWeekIso; // 1..7 (Mon..Sun)
        $isWeekend = in_array($dayOfWeek, [6, 7], true);

        $cv = strtoupper($conditionValue);
        return ($cv === 'WEEKEND' && $isWeekend) ||
            ($cv === 'WEEKDAY' && !$isWeekend);
    }

    /**
     * MORNING / AFTERNOON / EVENING / NIGHT.
     */
    private function checkTimeRange(string $conditionValue, $startTime): bool
    {
        $dt   = $startTime instanceof Carbon ? $startTime : Carbon::parse($startTime);
        $time = $dt->toTimeString(); // HH:MM:SS
        $h    = (int) substr($time, 0, 2);
        $m    = (int) substr($time, 3, 2);

        $cv = strtoupper($conditionValue);
        $minutes = $h * 60 + $m;

        // helper
        $between = fn(int $fromH, int $fromM, int $toH, int $toM): bool =>
        $minutes >= ($fromH * 60 + $fromM) && $minutes < ($toH * 60 + $toM);

        return match ($cv) {
            'MORNING'   => $between(6, 0, 12, 0),
            'AFTERNOON' => $between(12, 0, 17, 0),
            'EVENING'   => $between(17, 0, 22, 0),
            'NIGHT'     => $minutes >= 22 * 60 || $minutes < 6 * 60,
            default     => false,
        };
    }

    /**
     * check format 2D / 3D / IMAX / 4DX...
     */
    private function checkFormat(string $conditionValue, ?string $showtimeFormat): bool
    {
        if (!$showtimeFormat) {
            return false;
        }
        return str_contains(strtoupper($showtimeFormat), strtoupper($conditionValue));
    }

    private function checkRoomType(string $conditionValue, ?string $roomType): bool
    {
        if (!$roomType) {
            return false;
        }
        return strtoupper($roomType) === strtoupper($conditionValue);
    }

    private function checkSeatType(string $conditionValue, ?string $seatType): bool
    {
        if (!$seatType) {
            return false;
        }
        return strtoupper($seatType) === strtoupper($conditionValue);
    }

    /**
     * Tính tổng discount (membership + promotion).
     * Trả về DiscountResult (value object) giống bên Java.
     */
    public function calculateDiscounts(float $subtotal, ?string $userId, ?string $promotionCode): DiscountResult
    {
        $subtotalDec        = $subtotal;
        $totalDiscount      = 0.0;
        $membershipDiscount = 0.0;
        $promotionDiscount  = 0.0;
        $reasonParts        = [];

        // Membership
        if ($userId) {
            $user = User::find($userId);
            if ($user && $user->membershipTier) {
                /** @var MembershipTier $tier */
                $tier = $user->membershipTier;

                $membershipDiscount = $this->calculateMembershipDiscount($tier, $subtotalDec);
                if ($membershipDiscount > 0) {
                    $totalDiscount += $membershipDiscount;

                    $unit = strtoupper($tier->discount_type) === 'PERCENTAGE' ? '%' : ' VND';

                    $reasonParts[] = sprintf(
                        'Membership %s (-%s%s)',
                        $tier->name,
                        $tier->discount_value,
                        $unit
                    );

                    Log::debug('Applied membership discount: {d} for tier {t}', [
                        'd' => $membershipDiscount,
                        't' => $tier->name,
                    ]);
                }
            }
        }

        // Promotion
        if ($promotionCode && trim($promotionCode) !== '') {
            $promotion = Promotion::where('code', $promotionCode)->first();
            if ($promotion) {
                $promotionDiscount = $this->calculatePromotionDiscount($promotion, $subtotalDec);
                $totalDiscount    += $promotionDiscount;

                $reasonParts[] = 'Promotion: ' . $promotion->name;

                Log::debug('Applied promotion discount: {d} for code {code}', [
                    'd'    => $promotionDiscount,
                    'code' => $promotionCode,
                ]);
            }
        }

        return new DiscountResult(
            totalDiscount: round($totalDiscount, 2),
            membershipDiscount: round($membershipDiscount, 2),
            promotionDiscount: round($promotionDiscount, 2),
            discountReason: $reasonParts ? implode(', ', $reasonParts) : null,
        );
    }


    private function calculateMembershipDiscount(MembershipTier $tier, float $amount): float
    {
        if (!$tier->is_active) {
            return 0.0;
        }

        if (!$tier->discount_type || $tier->discount_value === null) {
            return 0.0;
        }

        if ($tier->discount_value <= 0) {
            return 0.0;
        }

        $type  = strtoupper($tier->discount_type);
        $value = (float) $tier->discount_value;

        return match ($type) {
            'PERCENTAGE'  => round($amount * $value / 100.0, 2),
            'FIXED_AMOUNT' => min($value, $amount),
            default       => 0.0,
        };
    }

    private function calculatePromotionDiscount(Promotion $promotion, float $amount): float
    {
        $type  = strtoupper($promotion->discount_type);
        $value = (float) $promotion->discount_value;

        return match ($type) {
            'PERCENTAGE'  => round($amount * $value / 100.0, 2),
            'FIXED_AMOUNT' => min($value, $amount),
            default       => 0.0,
        };
    }

    /**
     * Calculate total price với ticket type modifiers và promotions (Spring Boot spec compliant)
     */
    public function calculateTotalPrice(
        Showtime $showtime,
        \Illuminate\Support\Collection $showtimeSeats,
        array $seatRequestData,
        ?array $promotionIds = null
    ): array {
        $totalPrice = 0;
        $seatPrices = [];

        // Calculate base prices với ticket type modifiers
        foreach ($seatRequestData as $seatData) {
            $showtimeSeat = $showtimeSeats->firstWhere('showtime_seat_id', $seatData['showtimeSeatId']);
            if (!$showtimeSeat) {
                throw new \RuntimeException("Showtime seat not found: {$seatData['showtimeSeatId']}");
            }

            $ticketType = $this->getTicketType($seatData['ticketTypeId']);

            // Base price = seat price * ticket type modifier
            $basePrice = (float) $showtimeSeat->price;
            $modifier = (float) $ticketType->price_modifier; // VD: 0.8 cho HSSV/U22
            $finalPrice = $basePrice * $modifier;

            $totalPrice += $finalPrice;
            $seatPrices[] = [
                'showtimeSeatId' => $seatData['showtimeSeatId'],
                'ticketTypeId' => $seatData['ticketTypeId'],
                'basePrice' => $basePrice,
                'modifier' => $modifier,
                'finalPrice' => $finalPrice
            ];
        }

        // Apply promotions
        $discountAmount = 0;
        $discountReason = null;
        $appliedPromotions = [];

        if ($promotionIds && count($promotionIds) > 0) {
            $promotions = $this->getValidPromotions($promotionIds, $showtime->showtime_id);

            foreach ($promotions as $promotion) {
                $discount = $this->calculatePromotionDiscountAmount($promotion, $totalPrice);
                $discountAmount += $discount;
                $appliedPromotions[] = [
                    'promotionId' => $promotion->promotion_id,
                    'discountAmount' => $discount
                ];
            }

            $discountReason = implode(', ', $promotions->pluck('name')->toArray());
        }

        $finalPrice = max(0, $totalPrice - $discountAmount);

        return [
            'totalPrice' => $totalPrice,
            'discountAmount' => $discountAmount,
            'discountReason' => $discountReason,
            'finalPrice' => $finalPrice,
            'seatPrices' => $seatPrices,
            'appliedPromotions' => $appliedPromotions
        ];
    }

    private function getTicketType(string $ticketTypeId)
    {
        return \App\Models\TicketType::findOrFail($ticketTypeId);
    }

    private function getValidPromotions(array $promotionIds, string $showtimeId): \Illuminate\Support\Collection
    {
        return Promotion::whereIn('promotion_id', $promotionIds)
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();
    }

    private function calculatePromotionDiscountAmount($promotion, float $totalPrice): float
    {
        if ($promotion->discount_type === 'PERCENTAGE') {
            return $totalPrice * ($promotion->discount_value / 100);
        }

        return $promotion->discount_value; // FIXED amount
    }
}
