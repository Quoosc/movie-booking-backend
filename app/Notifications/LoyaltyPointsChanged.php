<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LoyaltyPointsChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $points,
        private readonly int $balance,
        private readonly string $description,
        private readonly ?string $newTier = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $gained = $this->points >= 0;
        return [
            'category' => 'LOYALTY',
            'title' => $this->newTier
                ? ($gained ? "Chúc mừng lên hạng {$this->newTier}" : "Hạng thành viên đã chuyển thành {$this->newTier}")
                : ($gained ? 'Bạn vừa nhận điểm thưởng' : 'Điểm thưởng đã được điều chỉnh'),
            'message' => $this->description . '. ' . ($gained ? '+' : '') . $this->points . ' điểm, số dư ' . $this->balance . ' điểm.',
            'points' => $this->points,
            'balance' => $this->balance,
            'newTier' => $this->newTier,
            'actionUrl' => '/account/account-member',
        ];
    }
}
