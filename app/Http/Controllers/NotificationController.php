<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->orderByDesc('id')->paginate(20);

        return view('notifications', ['notifications' => $notifications]);
    }

    public function markRead(Request $request, int $notification)
    {
        $notification = $this->notification($notification);

        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        return $notification->link ? redirect($notification->link) : back();
    }

    public function markAllRead()
    {
        auth()->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marquées comme lues.');
    }

    /** Compteur des notifications non lues (polling du badge navbar). */
    public function unreadCount()
    {
        return response()->json([
            'count' => auth()->user()->notifications()->whereNull('read_at')->count(),
        ]);
    }
}
