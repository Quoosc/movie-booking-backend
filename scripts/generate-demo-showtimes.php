<?php

// Dry-run by default. Adds missing daily showtimes without changing existing ones.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\TicketType;
use App\Modules\Pricing\Services\PriceCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$options = getopt('', ['from:', 'to:', 'per-movie:', 'apply', 'verify']);
$timezone = 'Asia/Ho_Chi_Minh';
$fromInput = $options['from'] ?? now($timezone)->toDateString();
$toInput = $options['to'] ?? '';
foreach ([$fromInput, $toInput] as $date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || CarbonImmutable::parse($date, $timezone)->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Use --from=YYYY-MM-DD --to=YYYY-MM-DD.');
    }
}
$from = CarbonImmutable::parse($fromInput, $timezone)->startOfDay();
$to = CarbonImmutable::parse($toInput, $timezone)->startOfDay();
$target = filter_var($options['per-movie'] ?? 5, FILTER_VALIDATE_INT);
if (!$target || $target < 1 || $target > 8 || $to < $from || $from->diffInDays($to) > 62) {
    throw new InvalidArgumentException('Invalid date range or daily count (1–8).');
}
$movies = Movie::where('status', 'SHOWING')->orderByDesc('duration')->orderBy('movie_id')->get();
$rooms = Room::with(['seats', 'cinema'])->where('is_active', true)
    ->whereHas('cinema', fn ($query) => $query->where('is_active', true))
    ->whereHas('seats')->orderBy('room_id')->get()->keyBy('room_id');
$tickets = TicketType::where('active', true)->orderBy('sort_order')->get();
if ($movies->isEmpty() || $rooms->isEmpty() || $tickets->isEmpty()) {
    throw new RuntimeException('Showing movies, active rooms with seats and ticket types are required.');
}
foreach ($movies as $movie) {
    if ($movie->duration < 1 || $movie->duration > 720) {
        throw new RuntimeException('Invalid duration: '.$movie->title);
    }
    if ($movie->premiere_date && $movie->premiere_date->toDateString() > $fromInput) {
        throw new RuntimeException('Showing movie has a future premiere date: '.$movie->title);
    }
}

$readExisting = function () use ($from, $to, $rooms) {
    return Showtime::with('movie')->whereIn('room_id', $rooms->keys())
        ->whereBetween('start_time', [
            $from->subDay()->utc()->toDateTimeString(),
            $to->addDays(2)->utc()->toDateTimeString(),
        ])->orderBy('start_time')->get();
};

$plan = function () use ($from, $to, $target, $rooms, $movies, $timezone, $readExisting) {
    $busy = [];
    $counts = [];
    $cinemaCounts = [];
    foreach ($readExisting() as $showtime) {
        $start = $showtime->start_time->getTimestamp();
        $busy[$showtime->room_id][] = [$start, $start + max(1, $showtime->movie->duration ?? 240) * 60];
        if ($showtime->is_active) {
            $day = $showtime->start_time->copy()->setTimezone($timezone)->toDateString();
            $counts[$day][$showtime->movie_id] = ($counts[$day][$showtime->movie_id] ?? 0) + 1;
            $cinema = $rooms[$showtime->room_id]->cinema_id;
            $cinemaCounts[$day][$showtime->movie_id][$cinema] = ($cinemaCounts[$day][$showtime->movie_id][$cinema] ?? 0) + 1;
        }
    }
    $rows = [];
    $daily = [];
    for ($day = $from; $day <= $to; $day = $day->addDay()) {
        $date = $day->toDateString();
        foreach ($movies as $movie) {
            if (($counts[$date][$movie->movie_id] ?? 0) > $target) {
                throw new RuntimeException("$date: existing count exceeds target for {$movie->title}; nothing will be deleted.");
            }
        }
        for ($wave = 0; $wave < $target; $wave++) {
            // Five daily waves start at 09:00, 11:45, 14:30, 17:15 and 20:00.
            $preferred = $day->addMinutes(540 + ($target > 1 ? (int) round(660 * $wave / ($target - 1)) : 0))->timestamp;
            foreach ($movies as $movie) {
                if (($counts[$date][$movie->movie_id] ?? 0) >= $target) {
                    continue;
                }
                $best = null;
                foreach ($rooms as $room) {
                    $candidate = $preferred;
                    $occupied = $busy[$room->room_id] ?? [];
                    usort($occupied, fn ($a, $b) => $a[0] <=> $b[0]);
                    foreach ($occupied as [$start, $end]) {
                        if ($candidate + $movie->duration * 60 + 900 <= $start) {
                            break;
                        }
                        if ($candidate < $end + 900 && $candidate + $movie->duration * 60 + 900 > $start) {
                            $candidate = (int) (ceil(($end + 900) / 300) * 300);
                        }
                    }
                    if ($candidate + $movie->duration * 60 > $day->addDay()->timestamp) {
                        continue;
                    }
                    $score = [$candidate, $cinemaCounts[$date][$movie->movie_id][$room->cinema_id] ?? 0,
                        count($occupied), $room->room_id];
                    if ($best === null || $score < $best['score']) {
                        $best = ['score' => $score, 'room' => $room, 'start' => $candidate];
                    }
                }
                if (!$best) {
                    throw new RuntimeException("Not enough room capacity on $date for {$movie->title}.");
                }
                $room = $best['room'];
                $busy[$room->room_id][] = [$best['start'], $best['start'] + $movie->duration * 60];
                $counts[$date][$movie->movie_id] = ($counts[$date][$movie->movie_id] ?? 0) + 1;
                $cinemaCounts[$date][$movie->movie_id][$room->cinema_id] = ($cinemaCounts[$date][$movie->movie_id][$room->cinema_id] ?? 0) + 1;
                $rows[] = ['showtime_id' => (string) Str::uuid(), 'movie_id' => $movie->movie_id,
                    'room_id' => $room->room_id, 'format' => '2D', 'is_active' => true,
                    'start_time' => CarbonImmutable::createFromTimestampUTC($best['start'])->toDateTimeString()];
                $daily[$date] = ($daily[$date] ?? 0) + 1;
            }
        }
        foreach ($movies as $movie) {
            if (($counts[$date][$movie->movie_id] ?? 0) !== $target) {
                throw new RuntimeException('Incomplete schedule: '.$date.' '.$movie->title);
            }
        }
    }
    return [$rows, $daily];
};

if (array_key_exists('verify', $options)) {
    $existing = $readExisting();
    $occupancy = [];
    $groups = [];
    $ids = [];
    foreach ($existing as $showtime) {
        $date = $showtime->start_time->copy()->setTimezone($timezone)->toDateString();
        $occupancy[$showtime->room_id][] = [$showtime->start_time->timestamp,
            $showtime->start_time->timestamp + ($showtime->movie->duration ?? 240) * 60];
        if ($date >= $fromInput && $date <= $toInput && $showtime->is_active && $movies->contains('movie_id', $showtime->movie_id)) {
            $groups[$date][$showtime->movie_id] = ($groups[$date][$showtime->movie_id] ?? 0) + 1;
            $ids[] = $showtime->showtime_id;
        }
    }
    foreach ($occupancy as $intervals) {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        for ($i = 1; $i < count($intervals); $i++) {
            if ($intervals[$i][0] < $intervals[$i - 1][1] + 900) {
                throw new RuntimeException('Room overlap or missing 15-minute cleanup buffer.');
            }
        }
    }
    for ($day = $from; $day <= $to; $day = $day->addDay()) {
        foreach ($movies as $movie) {
            if (($groups[$day->toDateString()][$movie->movie_id] ?? 0) !== $target) {
                throw new RuntimeException('Daily movie count mismatch: '.$day->toDateString().' '.$movie->title);
            }
        }
    }
    $inventory = DB::table('showtime_seats')->whereIn('showtime_id', $ids)
        ->selectRaw('showtime_id, COUNT(*) AS total, MIN(price) AS min_price')
        ->groupBy('showtime_id')->get()->keyBy('showtime_id');
    $assignments = DB::table('showtime_ticket_types')->whereIn('showtime_id', $ids)->where('active', true)
        ->selectRaw('showtime_id, COUNT(DISTINCT ticket_type_id) AS total')->groupBy('showtime_id')->get()->keyBy('showtime_id');
    foreach ($existing->whereIn('showtime_id', $ids) as $showtime) {
        if (($inventory[$showtime->showtime_id]->total ?? 0) !== $rooms[$showtime->room_id]->seats->count()
            || ($inventory[$showtime->showtime_id]->min_price ?? 0) <= 0
            || ($assignments[$showtime->showtime_id]->total ?? 0) !== $tickets->count()) {
            throw new RuntimeException('Incomplete seat/price/ticket inventory: '.$showtime->showtime_id);
        }
    }
    echo json_encode(['verified' => true, 'movies' => $movies->count(), 'days' => count($groups),
        'showtimes' => count($ids), 'seats' => $inventory->sum('total'), 'ticket_assignments' => $assignments->sum('total'),
        'room_conflicts' => 0, 'per_movie_per_day' => $target], JSON_PRETTY_PRINT)."\n";
    exit;
}

[$rows, $daily] = $plan();
echo json_encode(['mode' => array_key_exists('apply', $options) ? 'apply' : 'dry-run',
    'from' => $fromInput, 'through' => $toInput, 'timezone' => $timezone, 'showing_movies' => $movies->count(),
    'rooms_with_seats' => $rooms->count(), 'per_movie_per_day' => $target,
    'new_showtimes' => count($rows), 'daily_new_showtimes' => $daily], JSON_PRETTY_PRINT)."\n";
if (!array_key_exists('apply', $options) || !$rows) {
    exit;
}
$newIds = DB::transaction(function () use ($rooms, $plan, $tickets) {
    Room::whereIn('room_id', $rooms->keys())->orderBy('room_id')->lockForUpdate()->get();
    [$rows] = $plan(); // Recheck after acquiring the same room locks as the admin API.
    $pricing = app(PriceCalculationService::class);
    $now = now()->toDateTimeString();
    $ids = [];
    foreach ($rows as $index => $data) {
        $showtime = Showtime::create($data);
        $room = $rooms[$showtime->room_id];
        $showtime->setRelation('room', $room);
        $prices = [];
        $seatRows = [];
        foreach ($room->seats as $seat) {
            // Pricing conditions depend on seat type, not the individual seat ID.
            $prices[$seat->seat_type] ??= $pricing->calculatePriceWithBreakdown($showtime, $seat);
            [$price, $breakdown] = $prices[$seat->seat_type];
            if ($price <= 0) {
                throw new RuntimeException('Configured pricing returned a nonpositive price.');
            }
            $seatRows[] = ['showtime_seat_id' => (string) Str::uuid(), 'showtime_id' => $showtime->showtime_id,
                'seat_id' => $seat->seat_id, 'seat_status' => 'AVAILABLE', 'price' => $price,
                'price_breakdown' => $breakdown, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($seatRows, 200) as $chunk) {
            DB::table('showtime_seats')->insert($chunk);
        }
        $assignments = [];
        foreach ($tickets as $ticket) {
            $assignments[] = ['id' => (string) Str::uuid(), 'showtime_id' => $showtime->showtime_id,
                'ticket_type_id' => $ticket->id, 'active' => true, 'sort_order' => $ticket->sort_order,
                'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('showtime_ticket_types')->insert($assignments);
        $ids[] = $showtime->showtime_id;
        if (($index + 1) % 40 === 0) {
            echo 'Prepared '.($index + 1)." showtimes (transaction not committed yet).\n";
        }
    }
    return $ids;
});
$directory = storage_path('app/private/showtime-batches');
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$manifest = $directory.'/'.gmdate('Ymd-His').'-'.Str::uuid().'.json';
file_put_contents($manifest, json_encode(['from' => $fromInput, 'to' => $toInput, 'new_showtime_ids' => $newIds], JSON_PRETTY_PRINT));
echo 'COMMITTED '.count($newIds).' new showtimes; existing showtimes/bookings untouched. Manifest: '.$manifest."\n";
