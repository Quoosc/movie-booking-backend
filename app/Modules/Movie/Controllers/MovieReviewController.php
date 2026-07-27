<?php

namespace App\Modules\Movie\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Movie;
use App\Models\MovieReview;
use App\Models\ReviewReaction;
use App\Models\ReviewReport;
use App\Models\ReviewModerationLog;
use App\Notifications\ReviewReplyReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MovieReviewController extends BaseController
{
    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json(['code' => $code, 'message' => $message, 'data' => $data], $code);
    }

    // GET /movies/{movieId}/reviews
    public function index(string $movieId, Request $request)
    {
        Movie::findOrFail($movieId);
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'perPage' => 'sometimes|integer|min:1|max:50',
            'rating' => 'sometimes|integer|min:1|max:10',
            'sort' => 'sometimes|in:newest,oldest,highest,lowest,helpful',
            'verifiedOnly' => 'sometimes|boolean',
        ]);

        $query = MovieReview::query()
            ->where('movie_id', $movieId)
            ->whereNull('parent_id')
            ->where('status', 'PUBLISHED')
            ->with([
                'user:user_id,username,avatar_url',
                'reactions',
                'replies' => fn ($replyQuery) => $replyQuery
                    ->where('status', 'PUBLISHED')
                    ->oldest()
                    ->with(['user:user_id,username,avatar_url', 'reactions']),
            ])
            ->withCount('reactions');

        if (isset($validated['rating'])) {
            $query->where('rating', $validated['rating']);
        }

        if ($request->boolean('verifiedOnly')) {
            $query->whereNotNull('user_id')->whereHas('user', function ($userQuery) use ($movieId) {
                $userQuery->whereHas('bookings', function ($bookingQuery) use ($movieId) {
                    $bookingQuery
                        ->where('status', BookingStatus::CONFIRMED->value)
                        ->whereHas('showtime', fn ($showtimeQuery) => $showtimeQuery->where('movie_id', $movieId));
                });
            });
        }

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'highest' => $query->orderByDesc('rating')->latest(),
            'lowest' => $query->orderBy('rating')->latest(),
            'helpful' => $query->orderByDesc('reactions_count')->latest(),
            default => $query->latest(),
        };

        $paginator = $query->paginate($validated['perPage'] ?? 10);
        $verifiedUserIds = $this->verifiedUserIds($movieId, collect($paginator->items())->pluck('user_id'));
        $actorId = $request->user()?->user_id;
        $ip = $request->ip();

        $items = collect($paginator->items())
            ->map(fn (MovieReview $review) => $this->formatReview($review, $verifiedUserIds, $actorId, $ip))
            ->values();

        $summaryQuery = MovieReview::query()
            ->where('movie_id', $movieId)
            ->whereNull('parent_id')
            ->where('status', 'PUBLISHED');
        $summary = (clone $summaryQuery)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();
        $rawDistribution = (clone $summaryQuery)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return $this->respond([
            'items' => $items,
            'summary' => [
                'average' => $summary?->total ? round((float) $summary->average, 1) : null,
                'total' => (int) ($summary?->total ?? 0),
                'distribution' => collect(range(10, 1))->mapWithKeys(
                    fn ($rating) => [(string) $rating => (int) ($rawDistribution[$rating] ?? 0)]
                ),
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    // POST /movies/{movieId}/reviews
    public function store(string $movieId, Request $request)
    {
        Movie::findOrFail($movieId);
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:10',
            'comment' => 'required|string|min:1|max:5000',
            'reviewerName' => 'nullable|string|max:120',
            'imageUrl' => 'nullable|string|url|max:500',
            'isSpoiler' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        if ($user && MovieReview::where('movie_id', $movieId)->whereNull('parent_id')->where('user_id', $user->user_id)->exists()) {
            return $this->respond(null, 'Bạn đã đánh giá phim này. Hãy chỉnh sửa đánh giá cũ.', Response::HTTP_CONFLICT);
        }

        $reviewerName = $user?->username ?: ($data['reviewerName'] ?? 'Khách');
        $review = MovieReview::create([
            'movie_id' => $movieId,
            'user_id' => $user?->user_id,
            'reviewer_name' => $reviewerName,
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'image_url' => $data['imageUrl'] ?? null,
            'is_spoiler' => $data['isSpoiler'] ?? false,
            'status' => 'PUBLISHED',
        ]);
        $review->load(['user:user_id,username,avatar_url', 'reactions', 'replies']);
        $verified = $this->verifiedUserIds($movieId, collect([$review->user_id]));

        return $this->respond(
            $this->formatReview($review, $verified, $user?->user_id, $request->ip()),
            'Review created',
            Response::HTTP_CREATED
        );
    }

    // PUT /movies/{movieId}/reviews/{reviewId}
    public function update(string $movieId, string $reviewId, Request $request)
    {
        $review = MovieReview::where('movie_id', $movieId)->findOrFail($reviewId);
        $this->authorizeOwner($review, $request);
        $data = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:10',
            'comment' => 'sometimes|string|min:1|max:5000',
            'imageUrl' => 'sometimes|nullable|string|url|max:500',
            'isSpoiler' => 'sometimes|boolean',
        ]);

        $review->fill([
            ...array_key_exists('rating', $data) ? ['rating' => $data['rating']] : [],
            ...array_key_exists('comment', $data) ? ['comment' => $data['comment']] : [],
            ...array_key_exists('imageUrl', $data) ? ['image_url' => $data['imageUrl']] : [],
            ...array_key_exists('isSpoiler', $data) ? ['is_spoiler' => $data['isSpoiler']] : [],
            'edited_at' => now(),
        ])->save();
        $review->load(['user:user_id,username,avatar_url', 'reactions', 'replies']);

        return $this->respond($this->formatReview($review, collect(), $request->user()->user_id, $request->ip()), 'Review updated');
    }

    // DELETE /movies/{movieId}/reviews/{reviewId}
    public function destroy(string $movieId, string $reviewId, Request $request)
    {
        $review = MovieReview::where('movie_id', $movieId)->findOrFail($reviewId);
        $this->authorizeOwner($review, $request);
        $review->delete();

        return $this->respond(null, 'Review deleted');
    }

    // POST /movies/{movieId}/reviews/{reviewId}/react
    public function react(string $movieId, string $reviewId, Request $request)
    {
        $review = MovieReview::where('movie_id', $movieId)->where('status', 'PUBLISHED')->findOrFail($reviewId);
        $data = $request->validate(['reactionType' => 'required|in:LIKE,LOVE,HAHA,WOW,SAD']);
        $userId = $request->user()?->user_id;
        $ip = $request->ip();

        $currentQuery = ReviewReaction::where('review_id', $review->review_id);
        $current = $userId
            ? $currentQuery->where('user_id', $userId)->first()
            : $currentQuery->whereNull('user_id')->where('ip_address', $ip)->first();

        if ($current?->reaction_type === $data['reactionType']) {
            $current->delete();
        } elseif ($current) {
            $current->update(['reaction_type' => $data['reactionType']]);
        } else {
            ReviewReaction::create([
                'review_id' => $review->review_id,
                'reaction_type' => $data['reactionType'],
                'user_id' => $userId,
                'ip_address' => $userId ? null : $ip,
            ]);
        }

        $all = ReviewReaction::where('review_id', $review->review_id)->get();
        $mine = $userId
            ? $all->firstWhere('user_id', $userId)?->reaction_type
            : $all->whereNull('user_id')->firstWhere('ip_address', $ip)?->reaction_type;

        return $this->respond([
            'counts' => $all->groupBy('reaction_type')->map->count(),
            'myReaction' => $mine,
        ]);
    }

    // POST /movies/{movieId}/reviews/{reviewId}/reply
    public function reply(string $movieId, string $reviewId, Request $request)
    {
        $parent = MovieReview::where('movie_id', $movieId)
            ->whereNull('parent_id')
            ->where('status', 'PUBLISHED')
            ->findOrFail($reviewId);
        $data = $request->validate([
            'comment' => 'required|string|min:1|max:2000',
            'reviewerName' => 'nullable|string|max:120',
            'isSpoiler' => 'sometimes|boolean',
        ]);
        $user = $request->user();

        $reply = MovieReview::create([
            'movie_id' => $movieId,
            'parent_id' => $reviewId,
            'user_id' => $user?->user_id,
            'reviewer_name' => $user?->username ?: ($data['reviewerName'] ?? 'Khách'),
            'rating' => $parent->rating,
            'comment' => $data['comment'],
            'is_spoiler' => $data['isSpoiler'] ?? false,
            'status' => 'PUBLISHED',
        ]);
        $reply->load(['user:user_id,username,avatar_url', 'reactions', 'replies']);

        if ($parent->user_id && $parent->user_id !== $user?->user_id) {
            $parent->user?->notify(new ReviewReplyReceived(
                $movieId,
                $parent->movie?->title ?? 'phim',
                $reply->reviewer_name,
            ));
        }

        return $this->respond($this->formatReview($reply, collect(), $user?->user_id, $request->ip()), 'Reply created', 201);
    }

    // POST /movies/{movieId}/reviews/{reviewId}/report
    public function report(string $movieId, string $reviewId, Request $request)
    {
        $review = MovieReview::where('movie_id', $movieId)->findOrFail($reviewId);
        $data = $request->validate([
            'reason' => 'required|in:spam,spoiler,abuse,misinformation,other',
            'details' => 'nullable|string|max:1000',
        ]);
        $userId = $request->user()?->user_id;
        $identityQuery = ReviewReport::where('review_id', $review->review_id);
        $identityQuery = $userId
            ? $identityQuery->where('user_id', $userId)
            : $identityQuery->whereNull('user_id')->where('ip_address', $request->ip());

        if ($identityQuery->where('status', 'PENDING')->exists()) {
            return $this->respond(null, 'Bạn đã báo cáo đánh giá này.', Response::HTTP_CONFLICT);
        }

        ReviewReport::create([
            'review_id' => $review->review_id,
            'user_id' => $userId,
            'ip_address' => $userId ? null : $request->ip(),
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'PENDING',
        ]);

        return $this->respond(null, 'Cảm ơn bạn. Báo cáo đã được ghi nhận.', Response::HTTP_CREATED);
    }

    // ADMIN: GET /movies/reviews/moderation
    public function moderationIndex(Request $request)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:PUBLISHED,HIDDEN',
            'reportedOnly' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ]);
        $query = MovieReview::query()
            ->whereNull('parent_id')
            ->with([
                'movie:movie_id,title',
                'user:user_id,username,avatar_url',
                'reactions',
                'reports' => fn ($reportQuery) => $reportQuery->where('status', 'PENDING')->latest(),
                'latestModerationLog.moderator:user_id,username,email',
            ])
            ->withCount(['reports' => fn ($reportQuery) => $reportQuery->where('status', 'PENDING')]);

        if (isset($validated['status'])) $query->where('status', $validated['status']);
        if ($request->boolean('reportedOnly')) {
            $query->whereHas('reports', fn ($reportQuery) => $reportQuery->where('status', 'PENDING'));
        }

        $paginator = $query->orderByDesc('reports_count')->latest()->paginate($validated['perPage'] ?? 20);
        return $this->respond([
            'items' => collect($paginator->items())->map(function (MovieReview $review) {
                $moderation = $review->latestModerationLog;

                return [
                    ...$this->formatReview($review, collect(), null, null),
                    'movieTitle' => $review->movie?->title,
                    'reportCount' => (int) $review->reports_count,
                    'reports' => $review->reports->map(fn (ReviewReport $report) => [
                        'reportId' => $report->report_id,
                        'reason' => $report->reason,
                        'details' => $report->details,
                        'createdAt' => $report->created_at?->toISOString(),
                    ])->values(),
                    'lastModeration' => $moderation ? [
                        'action' => $moderation->action,
                        'previousStatus' => $moderation->previous_status,
                        'nextStatus' => $moderation->next_status,
                        'reason' => $moderation->reason,
                        'moderatorName' => $moderation->moderator?->username
                            ?? $moderation->moderator?->email
                            ?? 'Tài khoản quản trị đã xóa',
                        'createdAt' => $moderation->created_at?->toISOString(),
                    ] : null,
                ];
            }),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    // ADMIN: PATCH /movies/reviews/{reviewId}/moderate
    public function moderate(string $reviewId, Request $request)
    {
        $data = $request->validate([
            'status' => 'required|in:PUBLISHED,HIDDEN',
            'reason' => 'required|string|min:3|max:1000',
        ]);
        $review = MovieReview::findOrFail($reviewId);
        $previousStatus = $review->status;

        DB::transaction(function () use ($data, $previousStatus, $request, $review) {
            $review->update(['status' => $data['status']]);
            $review->reports()->where('status', 'PENDING')->update(['status' => 'RESOLVED']);

            ReviewModerationLog::create([
                'review_id' => $review->review_id,
                'moderator_id' => $request->user()?->user_id,
                'action' => $data['status'] === 'HIDDEN' ? 'HIDE' : 'RESTORE',
                'previous_status' => $previousStatus,
                'next_status' => $data['status'],
                'reason' => trim($data['reason']),
            ]);
        });

        return $this->respond([
            'reviewId' => $review->review_id,
            'status' => $review->status,
            'reason' => trim($data['reason']),
        ], 'Moderation updated');
    }

    private function verifiedUserIds(string $movieId, Collection $candidateIds): Collection
    {
        $ids = $candidateIds->filter()->unique()->values();
        if ($ids->isEmpty()) return collect();

        return Booking::query()
            ->whereIn('user_id', $ids)
            ->where('status', BookingStatus::CONFIRMED->value)
            ->whereHas('showtime', fn ($query) => $query->where('movie_id', $movieId))
            ->distinct()
            ->pluck('user_id');
    }

    private function authorizeOwner(MovieReview $review, Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($review->user_id === $user->user_id || strtoupper((string) $user->role) === 'ADMIN'), 403, 'Bạn không thể sửa đánh giá này.');
    }

    private function formatReview(
        MovieReview $review,
        Collection $verifiedUserIds,
        ?string $actorId,
        ?string $ip
    ): array {
        $user = $review->relationLoaded('user') ? $review->user : null;
        $reactions = $review->relationLoaded('reactions')
            ? $review->reactions->groupBy('reaction_type')->map->count()->toArray()
            : [];
        $myReaction = null;
        if ($review->relationLoaded('reactions')) {
            $mine = $actorId
                ? $review->reactions->firstWhere('user_id', $actorId)
                : $review->reactions->whereNull('user_id')->firstWhere('ip_address', $ip);
            $myReaction = $mine?->reaction_type;
        }
        $replies = $review->relationLoaded('replies')
            ? $review->replies->map(fn (MovieReview $reply) => $this->formatReview($reply, collect(), $actorId, $ip))->values()->toArray()
            : [];

        return [
            'reviewId' => $review->review_id,
            'parentId' => $review->parent_id,
            'movieId' => $review->movie_id,
            'userId' => $review->user_id,
            'reviewerName' => $review->reviewer_name ?? $user?->username ?? 'Khách',
            'avatarUrl' => $user?->avatar_url,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'imageUrl' => $review->image_url,
            'isSpoiler' => (bool) $review->is_spoiler,
            'status' => $review->status,
            'verifiedPurchase' => $review->user_id ? $verifiedUserIds->contains($review->user_id) : false,
            'canEdit' => $actorId && $review->user_id === $actorId,
            'reactions' => $reactions,
            'myReaction' => $myReaction,
            'replies' => $replies,
            'createdAt' => $review->created_at?->toISOString(),
            'editedAt' => $review->edited_at?->toISOString(),
        ];
    }
}
