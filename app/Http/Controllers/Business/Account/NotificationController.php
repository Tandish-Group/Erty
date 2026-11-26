<?php

namespace App\Http\Controllers\Business\Account;

use App\Http\Controllers\Controller;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The bell panel: the signed-in person's notifications (their own only), read state, and opening one.
 * Notifications are written by App\Services\NotificationCenter (and a few older notification classes).
 */
class NotificationController extends Controller
{
    /** Recent notifications for the panel, newest first, with the unread count. */
    public function index()
    {
        $user = auth()->user();
        $filter = request('filter') === 'unread' ? 'unread' : 'all';
        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();
        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $query->latest()->limit(40)->get()->map(fn ($n) => $this->present($n))->values(),
        ]);
    }

    /** Unread notifications (the toast poller). */
    public function unread()
    {
        return response()->json(auth()->user()->unreadNotifications()->latest()->limit(40)->get()->map(fn ($n) => $this->present($n))->values());
    }

    public function markAsRead($id)
    {
        auth()->user()->notifications()->find($id)?->markAsRead();
        return response()->json(['unread' => auth()->user()->unreadNotifications()->count()]);
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['unread' => 0]);
    }

    /** Open a notification: mark it read and go where it points. */
    public function open($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $this->present($notification)['url'];
        return redirect()->to($url && $this->isLocal($url) ? $url : route('dashboard.index'));
    }

    /** One notification as the panel shows it (also used for the older notification classes). */
    public function present(DatabaseNotification $n): array
    {
        $data = is_array($n->data) ? $n->data : (json_decode((string) $n->data, true) ?: []);
        $type = class_basename($n->type);
        $item = match ($type) {
            'ErtyNotification' => [
                'kind' => $data['kind'] ?? 'general', 'title' => $data['title'] ?? 'Notification', 'body' => $data['body'] ?? '',
                'url' => $data['url'] ?? null, 'icon' => $data['icon'] ?? 'bell', 'tone' => $data['tone'] ?? 'primary',
            ],
            default => ['kind' => 'general', 'title' => $data['title'] ?? 'Notification', 'body' => $data['message'] ?? ($data['body'] ?? ''), 'url' => $data['url'] ?? null, 'icon' => 'bell', 'tone' => 'primary'],
        };
        return $item + [
            'id' => $n->id,
            'actor' => $data['actor'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
            'time' => $n->created_at?->diffForHumans(),
            'open_url' => route('notifications.open', $n->id),
        ];
    }

    private function isLocal(string $url): bool
    {
        return str_starts_with($url, '/') || str_starts_with($url, url('/'));
    }
}
