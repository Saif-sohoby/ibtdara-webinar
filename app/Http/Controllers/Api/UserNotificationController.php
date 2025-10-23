<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserNotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Query params:
     *  - status: all|read|unread (default: unread)
     *  - page, per_page (default: 15)
     *  - since (ISO datetime, optional)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $status   = $request->query('status', 'unread');
            $perPage  = (int) $request->query('per_page', 10);
            $since    = $request->query('since');

            $q = Notification::query()
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', $user->getMorphClass())
                ->when($status === 'unread', fn($qq) => $qq->whereNull('read_at'))
                ->when($status === 'read',   fn($qq) => $qq->whereNotNull('read_at'))
                ->when($since, fn($qq) => $qq->where('created_at', '>=', $since))
                ->orderByDesc('created_at');

            $paginator = $q->paginate($perPage);

            // Transform each notification for a clean API contract
            $items = collect($paginator->items())->map(function (Notification $n) {
                $data       = $n->data ?? [];

                return [
                    'id'          => (string) $n->id,
                    'data'        => $data,
                    'type'        => $n->type,
                    'read'        => ! is_null($n->read_at),
                    'read_at'     => optional($n->read_at)?->toIso8601String(),
                    'created_at'  => optional($n->created_at)?->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'code'    => 200,
                'meta'    => [
                    'status'     => $status,
                    'current'    => $paginator->currentPage(),
                    'per_page'   => $paginator->perPage(),
                    'total'      => $paginator->total(),
                    'last_page'  => $paginator->lastPage(),
                    'next_page'  => $paginator->nextPageUrl(),
                ],
                'data'    => $items,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => 'Something went wrong.',
                'error'   => app()->environment('production') ? null : $e->getMessage(),
            ], 200);
        }
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function readSatusUpdate(Request $request, string $id)
    {
        try {
            $user = $request->user();

            $n = Notification::query()
                ->where('id', $id)
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', $user->getMorphClass())
                ->first();

            if (!$n) {
                return response()->json([
                    'success' => false,
                    'code'    => 404,
                    'message' => 'Notification not found.',
                ], 200);
            }

            if (is_null($n->read_at)) {
                $n->read_at = now();
                $n->save();
            }else{
                $n->read_at = null;
                $n->save();
            }

            return response()->json([
                'success' => true,
                'code'    => 200,
                'message' => 'Notification status updated.',
                'data'    => [
                    'id'       => (string) $n->id,
                    'read_at'  => optional($n->read_at)?->toIso8601String(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => 'Something went wrong.',
                'error'   => app()->environment('production') ? null : $e->getMessage(),
            ], 200);
        }
    }

    /**
     * POST /api/notifications/read-all
     */
    // public function markAllAsRead(Request $request)
    // {
    //     try {
    //         $user = $request->user();

    //         Notification::query()
    //             ->where('notifiable_id', $user->id)
    //             ->where('notifiable_type', $user->getMorphClass())
    //             ->whereNull('read_at')
    //             ->update(['read_at' => now()]);

    //         return response()->json([
    //             'success' => true,
    //             'code'    => 200,
    //             'message' => 'All notifications marked as read.',
    //         ], 200);
    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'success' => false,
    //             'code'    => 500,
    //             'message' => 'Something went wrong.',
    //             'error'   => app()->environment('production') ? null : $e->getMessage(),
    //         ], 200);
    //     }
    // }

    /**
     * DELETE /api/notifications/{id}
     */
    // public function destroy(Request $request, string $id)
    // {
    //     try {
    //         $user = $request->user();

    //         $n = Notification::query()
    //             ->where('id', $id)
    //             ->where('notifiable_id', $user->id)
    //             ->where('notifiable_type', $user->getMorphClass())
    //             ->first();

    //         if (! $n) {
    //             return response()->json([
    //                 'success' => false,
    //                 'code'    => 404,
    //                 'message' => 'Notification not found.',
    //             ], 200);
    //         }

    //         $n->delete();

    //         return response()->json([
    //             'success' => true,
    //             'code'    => 200,
    //             'message' => 'Notification deleted.',
    //         ], 200);
    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'success' => false,
    //             'code'    => 500,
    //             'message' => 'Something went wrong.',
    //             'error'   => app()->environment('production') ? null : $e->getMessage(),
    //         ], 200);
    //     }
    // }

    public function show(Request $request, string $id)
    {
        try {
            $user = $request->user();

            $n = Notification::query()
                ->where('id', $id)
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', $user->getMorphClass())
                ->first();

            if (!$n) {
                return response()->json([
                    'success' => false,
                    'code'    => 404,
                    'message' => 'Notification not found.',
                ], 200);
            }

            $data = $n->data ?? [];

            return response()->json([
                'success' => true,
                'code'    => 200,
                'data'    => [
                    'id'          => (string) $n->id,
                    'data'        => $data,
                    'type'        => $n->type,
                    'read'        => ! is_null($n->read_at),
                    'read_at'     => optional($n->read_at)?->toIso8601String(),
                    'created_at'  => optional($n->created_at)?->toIso8601String(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => 'Something went wrong.',
                'error'   => app()->environment('production') ? null : $e->getMessage(),
            ], 200);
        }
    }
}
