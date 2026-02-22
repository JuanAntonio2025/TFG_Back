<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryController extends Controller
{
    /**
     * Biblioteca del usuario: libros comprados en pedidos pagados.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = DB::table('orders')
            ->join('book_order', 'orders.order_id', '=', 'book_order.order_id')
            ->join('books', 'books.book_id', '=', 'book_order.book_id')
            ->where('orders.user_id', $user->user_id)
            ->where('orders.status', 'paid')
            ->select(
                'books.book_id',
                'books.title',
                'books.author',
                'books.front_page',
                'books.format',
                'books.available',
                'book_order.unit_price',
                'orders.order_id',
                'orders.order_date'
            )
            ->orderByDesc('orders.order_date')
            ->get();

        // Si un usuario compró el mismo libro en pedidos distintos (raro en ebooks),
        // puedes devolver todos los registros o deduplicar. Aquí deduplicamos por book_id.
        $library = collect($rows)
            ->unique('book_id')
            ->values()
            ->map(function ($row) {
                return [
                    'book_id' => $row->book_id,
                    'title' => $row->title,
                    'author' => $row->author,
                    'front_page' => $row->front_page,
                    'format' => $row->format,
                    'available' => $row->available,
                    'purchase_info' => [
                        'order_id' => $row->order_id,
                        'purchased_at' => $row->order_date,
                        'unit_price' => (float) $row->unit_price,
                    ],
                ];
            });

        return response()->json([
            'data' => $library,
        ]);
    }
}
