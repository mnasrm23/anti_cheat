<?php

namespace App\Http\Controllers;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(10);
        return response()->json([
            'status' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => $notifications
        ]);
    }

    public function unreadCount()
    {
        return response()->json([
            'status' => true,
            'message' => 'Unread count retrieved successfully',
            'data' => ['count' => auth()->user()->unreadNotifications()->count()]
        ]);
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->update(['read' => true]);
        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read',
            'data' => null
        ]);
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications()->update(['read' => true]);
        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read',
            'data' => null
        ]);
    }

    public function destroy($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->delete();
        return response()->json([
            'status' => true,
            'message' => 'Notification deleted successfully',
            'data' => null
        ]);
    }

    public function destroyAll()
    {
        auth()->user()->notifications()->delete();
        return response()->json([
            'status' => true,
            'message' => 'All notifications deleted successfully',
            'data' => null
        ]);
    }
}
