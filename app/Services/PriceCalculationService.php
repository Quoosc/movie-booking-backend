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
}

// namespace App\Services;

// use App\Models\PriceBase;
// use App\Models\PriceModifier;
// use App\Models\Promotion;
// use App\Models\Seat;
// use App\Models\Showtime;
// use App\Models\User;
// use App\Models\MembershipTier;
// use App\ValueObjects\PriceBreakdown;
// use App\ValueObjects\DiscountResult;
// use Illuminate\Support\Facades\Log;
// use App\Exceptions\CustomException;

// class PriceCalculationService
// {
//     public function __construct(
//         protected PriceBase     $priceBaseModel,
//         protected PriceModifier $priceModifierModel,
//         protected User          $userModel,
//         protected Promotion     $promotionModel,
//     ) {}

//     /**
//      * Trả về [finalPrice, PriceBreakdown]
//      */
//     public function calculatePriceWithBreakdown(Showtime $showtime, Seat $seat): array
//     {
//         $priceBase = $this->priceBaseModel
//             ->newQuery()
//             ->where('is_active', true)
//             ->first();

//         if (!$priceBase) {
//             throw new CustomException('No active base price configured');
//         }

//         $basePriceValue = (float) $priceBase->base_price;
//         $finalPrice = $basePriceValue;

//         $breakdown = new PriceBreakdown(
//             basePrice: $basePriceValue,
//             modifiers: [],
//             finalPrice: $basePriceValue
//         );

//         $modifiers = $this->priceModifierModel
//             ->newQuery()
//             ->where('is_active', true)
//             ->get();

//         $applicableModifiers = [];

//         foreach ($modifiers as $modifier) {
//             if ($this->isModifierApplicable($modifier, $showtime, $seat)) {
//                 $applicableModifiers[] = $modifier;
//             }
//         }

//         foreach ($applicableModifiers as $modifier) {
//             $before = $finalPrice;
//             $finalPrice = $this->applyModifier($finalPrice, $modifier);
//             $change = $finalPrice - $before;

//             $breakdown->addModifier(
//                 name: $modifier->name,
//                 type: $modifier->condition_type . ':' . $modifier->condition_value,
//                 value: $change
//             );
//         }

//         $finalPrice = round($finalPrice, 2);
//         $breakdown->finalPrice = $finalPrice;

//         return [$finalPrice, $breakdown];
//     }

//     public function calculatePrice(Showtime $showtime, Seat $seat): float
//     {
//         [$price, ] = $this->calculatePriceWithBreakdown($showtime, $seat);
//         return $price;
//     }

//     protected function isModifierApplicable(PriceModifier $modifier, Showtime $showtime, Seat $seat): bool
//     {
//         $conditionType = $modifier->condition_type;
//         $value = $modifier->condition_value;

//         switch ($conditionType) {
//             case 'DAY_TYPE':
//                 return $this->checkDayType($value, $showtime->start_time);

//             case 'TIME_RANGE':
//                 return $this->checkTimeRange($value, $showtime->start_time);

//             case 'FORMAT':
//                 return $this->checkFormat($value, $showtime->format);

//             case 'ROOM_TYPE':
//                 return $this->checkRoomType($value, $showtime->room->room_type ?? null);

//             case 'SEAT_TYPE':
//                 return $this->checkSeatType($value, $seat->seat_type);

//             default:
//                 return false;
//         }
//     }

//     protected function applyModifier(float $currentPrice, PriceModifier $modifier): float
//     {
//         if ($modifier->modifier_type === 'PERCENTAGE') {
//             $multiplier = 1.0 + ((float) $modifier->modifier_value / 100.0);
//             return $currentPrice * $multiplier;
//         }

//         // FIXED_AMOUNT
//         return $currentPrice + (float) $modifier->modifier_value;
//     }

//     protected function checkDayType(string $conditionValue, \Carbon\Carbon $startTime): bool
//     {
//         $isWeekend = $startTime->isWeekend();

//         return ($conditionValue === 'WEEKEND' && $isWeekend)
//             || ($conditionValue === 'WEEKDAY' && !$isWeekend);
//     }

//     protected function checkTimeRange(string $conditionValue, \Carbon\Carbon $startTime): bool
//     {
//         $time = $startTime->copy()->setDate(1970, 1, 1);

//         $ranges = [
//             'MORNING'   => ['06:00', '12:00'],
//             'AFTERNOON' => ['12:00', '17:00'],
//             'EVENING'   => ['17:00', '22:00'],
//             'NIGHT'     => ['22:00', '06:00'],
//         ];

//         $key = strtoupper($conditionValue);

//         if (!isset($ranges[$key])) {
//             return false;
//         }

//         [$from, $to] = $ranges[$key];

//         $fromTime = \Carbon\Carbon::createFromTimeString($from);
//         $toTime = \Carbon\Carbon::createFromTimeString($to);

//         if ($key === 'NIGHT') {
//             // 22:00 – 06:00 (qua ngày)
//             return $time->greaterThanOrEqualTo($fromTime) || $time->lessThan($toTime);
//         }

//         return $time->greaterThanOrEqualTo($fromTime) && $time->lessThan($toTime);
//     }

//     protected function checkFormat(?string $conditionValue, ?string $showtimeFormat): bool
//     {
//         if (!$showtimeFormat || !$conditionValue) {
//             return false;
//         }

//         return str_contains(strtoupper($showtimeFormat), strtoupper($conditionValue));
//     }

//     protected function checkRoomType(?string $conditionValue, ?string $roomType): bool
//     {
//         if (!$roomType || !$conditionValue) {
//             return false;
//         }

//         return strcasecmp($roomType, $conditionValue) === 0;
//     }

//     protected function checkSeatType(?string $conditionValue, ?string $seatType): bool
//     {
//         if (!$seatType || !$conditionValue) {
//             return false;
//         }

//         return strcasecmp($seatType, $conditionValue) === 0;
//     }

//     // ==================== DISCOUNTS ====================

//     public function calculateDiscounts(float $subtotal, ?string $userId, ?string $promotionCode): DiscountResult
//     {
//         $totalDiscount = 0.0;
//         $membershipDiscount = 0.0;
//         $promotionDiscount = 0.0;
//         $reasonParts = [];

//         // Membership
//         if ($userId) {
//             $user = $this->userModel->newQuery()->find($userId);
//             if ($user && $user->membershipTier) {
//                 $membershipDiscount = $this->calculateMembershipDiscount($user->membershipTier, $subtotal);
//                 if ($membershipDiscount > 0) {
//                     $totalDiscount += $membershipDiscount;
//                     $tier = $user->membershipTier;
//                     $suffix = $tier->discount_type === 'PERCENTAGE' ? '%' : ' VND';
//                     $reasonParts[] = 'Membership ' . $tier->name
//                         . ' (-' . $tier->discount_value . $suffix . ')';
//                 }
//             }
//         }

//         // Promotion
//         if ($promotionCode && trim($promotionCode) !== '') {
//             $promotion = $this->promotionModel
//                 ->newQuery()
//                 ->where('code', $promotionCode)
//                 ->first();

//             if ($promotion) {
//                 $promotionDiscount = $this->calculatePromotionDiscount($promotion, $subtotal);
//                 $totalDiscount += $promotionDiscount;
//                 $reasonParts[] = 'Promotion: ' . $promotion->name;
//             }
//         }

//         return new DiscountResult(
//             totalDiscount: $totalDiscount,
//             membershipDiscount: $membershipDiscount,
//             promotionDiscount: $promotionDiscount,
//             discountReason: !empty($reasonParts) ? implode(', ', $reasonParts) : null,
//         );
//     }

//     protected function calculateMembershipDiscount(MembershipTier $tier, float $amount): float
//     {
//         if (!$tier->is_active) {
//             return 0.0;
//         }

//         if (!$tier->discount_type || $tier->discount_value <= 0) {
//             return 0.0;
//         }

//         if ($tier->discount_type === 'PERCENTAGE') {
//             return round($amount * ($tier->discount_value / 100), 2);
//         }

//         // FIXED_AMOUNT
//         return min($tier->discount_value, $amount);
//     }

//     protected function calculatePromotionDiscount(Promotion $promotion, float $amount): float
//     {
//         if ($promotion->discount_type === 'PERCENTAGE') {
//             return round($amount * ($promotion->discount_value / 100), 2);
//         }

//         return min($promotion->discount_value, $amount);
//     }
// }
