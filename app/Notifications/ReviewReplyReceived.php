<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewReplyReceived extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $movieId,
        private readonly string $movieTitle,
        private readonly string $replierName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'REVIEW',
            'title' => 'Có phản hồi mới',
            'message' => $this->replierName . ' đã phản hồi đánh giá của bạn về phim ' . $this->movieTitle . '.',
            'movieId' => $this->movieId,
            'actionUrl' => '/movie/' . $this->movieId . '?tab=reviews',
        ];
    }
}
