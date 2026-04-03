<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SupportUserController extends Controller
{
    public function summary(int $userId): JsonResponse
    {
        $user = User::select('user_id', 'name', 'email', 'status', 'register_date')
            ->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        $orders = DB::table('orders')
            ->where('user_id', $userId)
            ->orderByDesc('order_date')
            ->get([
                'order_id',
                'order_date',
                'total_amount',
                'status',
            ]);

        $purchasedBooks = DB::table('orders')
            ->join('book_order', 'orders.order_id', '=', 'book_order.order_id')
            ->join('books', 'book_order.book_id', '=', 'books.book_id')
            ->where('orders.user_id', $userId)
            ->where('orders.status', 'paid')
            ->orderByDesc('orders.order_date')
            ->get([
                'books.book_id',
                'books.title',
                'books.author',
                'books.format',
                'books.front_page',
                'book_order.unit_price',
                'orders.order_date as purchased_at',
            ]);

        return response()->json([
            'data' => [
                'user' => $user,
                'orders' => $orders,
                'books' => $purchasedBooks,
            ],
        ]);
    }
}
