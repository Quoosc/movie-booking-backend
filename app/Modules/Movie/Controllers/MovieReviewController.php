<?php

namespace App\Modules\Movie\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\Movie;
use App\Models\MovieReview;
use App\Models\ReviewReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MovieReviewController extends BaseController
{
    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json(['code' => $code, 'message' => $message, 'data' => $data], $code);
    }

    // GET /movies/{movieId}/reviews
    public function index(string $movieId)
    {
        Movie::findOrFail($movieId);

        $reviews = MovieReview::where('movie_id', $movieId)
            ->whereNull('parent_id')
            ->with(['user:user_id,username,avatar_url', 'reactions', 'replies.user:user_id,username,avatar_url', 'replies.reactions'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => $this->formatReview($r));

        return $this->respond($reviews);
    }

    // POST /movies/{movieId}/reviews
    public function store(string $movieId, Request $request)
    {
        Movie::findOrFail($movieId);

        $data = $request->validate([
            'rating'       => 'required|integer|min:1|max:10',
            'comment'      => 'required|string|min:1|max:5000',
            'reviewerName' => 'nullable|string|max:120',
            'imageUrl'     => 'nullable|string|url|max:500',
        ]);

        $userId = null;
        $reviewerName = $data['reviewerName'] ?? null;

        try {
            $user = Auth::guard('api')->user();
            if ($user) {
                $userId = $user->user_id;
                $reviewerName = $user->username;
            }
        } catch (\Throwable) {
            // Guest — không có token
        }

        if (!$reviewerName) {
            $reviewerName = 'Khách';
        }

        $review = MovieReview::create([
            'movie_id'      => $movieId,
            'user_id'       => $userId,
            'reviewer_name' => $reviewerName,
            'rating'        => $data['rating'],
            'comment'       => $data['comment'],
            'image_url'     => $data['imageUrl'] ?? null,
        ]);

        $review->load('user:user_id,username,avatar_url');

        return $this->respond($this->formatReview($review), 'Review created', Response::HTTP_CREATED);
    }

    // POST /movies/{movieId}/reviews/{reviewId}/react
    public function react(string $movieId, string $reviewId, Request $request)
    {
        Movie::findOrFail($movieId);
        MovieReview::findOrFail($reviewId);

        $data = $request->validate([
            'reactionType' => 'required|in:LIKE,LOVE,HAHA,WOW,SAD',
        ]);
        $userId = null;
        try {
            $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
            if ($user) $userId = $user->user_id;
        } catch (\Throwable) {}

        $ip = $request->ip();
        $newType = $data['reactionType'];

        // Lấy reaction hiện tại của user/IP trên review này (bất kỳ loại nào)
        $baseQuery = ReviewReaction::where('review_id', $reviewId);
        $currentReaction = $userId
            ? $baseQuery->where('user_id', $userId)->first()
            : $baseQuery->where('ip_address', $ip)->first();

        if ($currentReaction) {
            if ($currentReaction->reaction_type === $newType) {
                // Bấm lại cùng loại → bỏ reaction
                $currentReaction->delete();
            } else {
                // Đổi sang loại khác → cập nhật
                $currentReaction->update(['reaction_type' => $newType]);
            }
        } else {
            ReviewReaction::create([
                'review_id'     => $reviewId,
                'reaction_type' => $newType,
                'user_id'       => $userId,
                'ip_address'    => $userId ? null : $ip,
            ]);
        }

        // Return updated counts + myReaction
        $allReactions = ReviewReaction::where('review_id', $reviewId)->get();
        $counts = $allReactions->groupBy('reaction_type')->map(fn($g) => $g->count())->toArray();
        $mine = $userId
            ? $allReactions->firstWhere('user_id', $userId)?->reaction_type
            : $allReactions->firstWhere('ip_address', $ip)?->reaction_type;

        return $this->respond(['counts' => $counts, 'myReaction' => $mine], 'OK');
    }

    // POST /movies/{movieId}/reviews/{reviewId}/reply
    public function reply(string $movieId, string $reviewId, Request $request)
    {
        Movie::findOrFail($movieId);
        $parent = MovieReview::whereNull('parent_id')->findOrFail($reviewId);

        $data = $request->validate([
            'comment'      => 'required|string|min:1|max:2000',
            'reviewerName' => 'nullable|string|max:120',
        ]);

        $userId = null;
        $reviewerName = $data['reviewerName'] ?? null;
        try {
            $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
            if ($user) { $userId = $user->user_id; $reviewerName = $user->username; }
        } catch (\Throwable) {}
        if (!$reviewerName) $reviewerName = 'Khách';

        $reply = MovieReview::create([
            'movie_id'      => $movieId,
            'parent_id'     => $reviewId,
            'user_id'       => $userId,
            'reviewer_name' => $reviewerName,
            'rating'        => $parent->rating, // inherit parent rating
            'comment'       => $data['comment'],
        ]);

        $reply->load('user:user_id,username,avatar_url');
        return $this->respond($this->formatReview($reply), 'Reply created', 201);
    }

    private function formatReview(MovieReview $r): array
    {
        $user = $r->relationLoaded('user') ? $r->user : null;

        // Group reactions by type + counts
        $reactions = [];
        if ($r->relationLoaded('reactions')) {
            $reactions = $r->reactions->groupBy('reaction_type')
                ->map(fn($group) => $group->count())
                ->toArray();
        }

        // Format replies
        $replies = [];
        if ($r->relationLoaded('replies')) {
            $replies = $r->replies->map(fn($reply) => $this->formatReview($reply))->values()->toArray();
        }

        return [
            'reviewId'     => $r->review_id,
            'parentId'     => $r->parent_id,
            'movieId'      => $r->movie_id,
            'userId'       => $r->user_id,
            'reviewerName' => $r->reviewer_name ?? $user?->username ?? 'Khách',
            'avatarUrl'    => $user?->avatar_url ?? null,
            'rating'       => $r->rating,
            'comment'      => $r->comment,
            'imageUrl'     => $r->image_url,
            'reactions'    => $reactions,
            'replies'      => $replies,
            'createdAt'    => $r->created_at?->toISOString(),
        ];
    }
}
