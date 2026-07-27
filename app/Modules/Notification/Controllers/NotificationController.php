<?php

namespace App\Modules\Notification\Controllers;

use App\Core\Http\Controllers\BaseController;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json(['code' => $code, 'message' => $message, 'data' => $data], $code);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'perPage' => 'sometimes|integer|min:1|max:50',
            'unreadOnly' => 'sometimes|boolean',
        ]);
        $query = $request->user()->notifications();
        if ($request->boolean('unreadOnly')) $query->whereNull('read_at');
        $paginator = $query->paginate($data['perPage'] ?? 15);

        return $this->respond([
            'items' => collect($paginator->items())->map(fn ($notification) => [
                'notificationId' => $notification->id,
                'category' => $notification->data['category'] ?? 'SYSTEM',
                'title' => $notification->data['title'] ?? 'Thông báo',
                'message' => $notification->data['message'] ?? '',
                'actionUrl' => $notification->data['actionUrl'] ?? null,
                'data' => $notification->data,
                'isRead' => $notification->read_at !== null,
                'readAt' => $notification->read_at?->toISOString(),
                'createdAt' => $notification->created_at?->toISOString(),
            ]),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request)
    {
        return $this->respond(['unreadCount' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(string $notificationId, Request $request)
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();
        return $this->respond(['notificationId' => $notification->id, 'isRead' => true]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return $this->respond(null, 'All notifications marked as read');
    }

    public function destroy(string $notificationId, Request $request)
    {
        $request->user()->notifications()->findOrFail($notificationId)->delete();
        return $this->respond(null, 'Notification deleted');
    }
}
