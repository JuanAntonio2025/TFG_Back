<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'books'])
            ->orderByDesc('order_id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $orders = $query->get()->map(function (Order $order) {
            return $this->formatOrder($order);
        });

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function show(int $orderId): JsonResponse
    {
        $order = Order::with(['user', 'books'])
            ->find($orderId);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatOrder($order),
        ]);
    }

    private function formatOrder(Order $order): array
    {
        $items = $order->books->map(function ($book) {
            $unitPrice = isset($book->pivot->unit_price)
                ? (float) $book->pivot->unit_price
                : (float) $book->price;

            return [
                'book_id' => $book->book_id,
                'title' => $book->title,
                'author' => $book->author,
                'front_page' => $book->front_page,
                'front_page_url' => $book->front_page
                    ? Storage::disk('jupiter_covers')->url($book->front_page)
                    : null,
                'format' => $book->format,
                'unit_price' => round($unitPrice, 2),
            ];
        })->values();

        return [
            'order_id' => $order->order_id,
            'user_id' => $order->user_id,
            'user' => $order->user ? [
                'user_id' => $order->user->user_id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ] : null,
            'order_date' => $order->order_date,
            'total_amount' => (float) $order->total_amount,
            'status' => $order->status,
            'items_count' => $items->count(),
            'items' => $items,
        ];
    }
}
