<?php

namespace App\Console\Commands;

use App\Models\{SeatLock, ShowtimeSeat, Booking};
use App\Enums\SeatStatus;
use App\Enums\BookingStatus;
use App\Services\RedisLockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupExpiredSeatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'locks:cleanup-expired';

    /**
     * The console command description.
     */
    protected $description = 'Delete expired seat locks, update showtime_seats status to AVAILABLE, release Redis locks, and expire pending bookings';

    public function __construct(
        protected RedisLockService $redisLockService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting cleanup of expired seat locks and bookings...');

        try {
            DB::transaction(function () {
                $now = Carbon::now();

                // Cleanup expired locks
                $expiredLocks = SeatLock::where('expires_at', '<=', $now)
                    ->with('seatLockSeats.showtimeSeat')
                    ->get();

                foreach ($expiredLocks as $lock) {
                    $showtimeSeatIds = $lock->seatLockSeats->pluck('showtime_seat_id')->toArray();

                    if (!empty($showtimeSeatIds)) {
                        ShowtimeSeat::whereIn('showtime_seat_id', $showtimeSeatIds)
                            ->update(['seat_status' => SeatStatus::AVAILABLE->value]);

                        foreach ($showtimeSeatIds as $showtimeSeatId) {
                            $redisKey = $this->redisLockService->generateSeatLockKey(
                                $lock->showtime_id,
                                $showtimeSeatId
                            );
                            \Illuminate\Support\Facades\Redis::del($redisKey);
                        }
                    }

                    $lock->seatLockSeats()->delete();
                    $lock->delete();
                }

                // Expire pending bookings past payment_expires_at
                $timeoutMinutes = config('booking.payment.timeout.minutes', 15);
                $expiredBookings = Booking::where('status', BookingStatus::PENDING_PAYMENT)
                    ->where(function ($q) use ($now, $timeoutMinutes) {
                        $q->where('payment_expires_at', '<=', $now)
                          ->orWhere(function ($q2) use ($now, $timeoutMinutes) {
                              // Fallback: if payment_expires_at is null, use booked_at + timeout
                              $q2->whereNull('payment_expires_at')
                                 ->where('booked_at', '<=', $now->copy()->subMinutes($timeoutMinutes));
                          });
                    })
                    ->with('bookingSeats')
                    ->get();

                foreach ($expiredBookings as $booking) {
                    $seatIds = $booking->bookingSeats->pluck('showtime_seat_id')->toArray();

                    if (!empty($seatIds)) {
                        ShowtimeSeat::whereIn('showtime_seat_id', $seatIds)
                            ->update(['seat_status' => SeatStatus::AVAILABLE->value]);
                    }

                    $booking->status = BookingStatus::EXPIRED;
                    $booking->qr_payload = null;
                    $booking->qr_code = null;
                    // set payment_expires_at if missing to make next passes idempotent
                    if (!$booking->payment_expires_at) {
                        $booking->payment_expires_at = $booking->booked_at?->copy()->addMinutes($timeoutMinutes) ?? $now;
                    }
                    $booking->save();
                }
            });

            $this->info('Cleanup finished.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error during cleanup: {$e->getMessage()}");
            Log::error('Cleanup expired locks/bookings failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
