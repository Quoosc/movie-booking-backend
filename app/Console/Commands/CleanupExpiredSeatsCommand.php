<?php

namespace App\Console\Commands;

use App\Models\{SeatLock, ShowtimeSeat};
use App\Enums\SeatStatus;
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
    protected $description = 'Delete expired seat locks, update showtime_seats status to AVAILABLE, and release Redis locks';

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
        $this->info('Starting cleanup of expired seat locks...');

        try {
            DB::transaction(function () {
                $now = Carbon::now();

                // Find all expired locks
                $expiredLocks = SeatLock::where('expires_at', '<=', $now)
                    ->with('seatLockSeats.showtimeSeat')
                    ->get();

                if ($expiredLocks->isEmpty()) {
                    $this->info('No expired locks found.');
                    return;
                }

                $this->info("Found {$expiredLocks->count()} expired locks to clean up.");

                foreach ($expiredLocks as $lock) {
                    // Get all showtime seat IDs from this lock
                    $showtimeSeatIds = $lock->seatLockSeats->pluck('showtime_seat_id')->toArray();

                    if (!empty($showtimeSeatIds)) {
                        // Update showtime_seats status back to AVAILABLE
                        ShowtimeSeat::whereIn('showtime_seat_id', $showtimeSeatIds)
                            ->update(['status' => SeatStatus::AVAILABLE]);

                        $this->line("  → Updated {count($showtimeSeatIds)} seats to AVAILABLE for lock {$lock->seat_lock_id}");

                        // Release Redis locks by deleting keys
                        foreach ($showtimeSeatIds as $showtimeSeatId) {
                            $redisKey = "seat_lock:{$lock->showtime_id}:{$showtimeSeatId}";
                            \Illuminate\Support\Facades\Redis::del($redisKey);
                        }

                        $this->line("  → Released Redis locks for lock {$lock->seat_lock_id}");
                    }

                    // Delete seat_lock_seats records
                    $lock->seatLockSeats()->delete();

                    // Delete the seat_lock itself
                    $lock->delete();

                    $this->line("  → Deleted lock {$lock->seat_lock_id}");
                }

                $this->info("Successfully cleaned up {$expiredLocks->count()} expired locks.");
            });

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error during cleanup: {$e->getMessage()}");
            Log::error('Cleanup expired locks failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
