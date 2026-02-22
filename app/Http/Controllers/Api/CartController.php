<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Obtiene el carrito activo del usuario autenticado (si existe).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $cart = Cart::with(['books'])
            ->where('user_id', $user->user_id)
            ->where('active', Cart::STATUS_ACTIVE ?? 'active')
            ->latest('cart_id')
            ->first();

        if (!$cart) {
            return response()->json([
                'data' => [
                    'cart' => null,
                    'items' => [],
                    'summary' => [
                        'items_count' => 0,
                        'total_quantity' => 0,
                        'total_amount' => 0.00,
                    ],
                ],
            ]);
        }

        return response()->json([
            'data' => $this->formatCartResponse($cart),
        ]);
    }

    /**
     * Añade un libro al carrito activo (o crea carrito si no existe).
     */
    public function storeItem(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,book_id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $book = Book::where('book_id', $data['book_id'])
            ->where('available', 'available')
            ->first();

        if (!$book) {
            return response()->json([
                'message' => 'Book is not available.',
            ], 422);
        }

        DB::transaction(function () use ($user, $data) {
            $cart = Cart::where('user_id', $user->user_id)
                ->where('active', Cart::STATUS_ACTIVE ?? 'active')
                ->latest('cart_id')
                ->lockForUpdate()
                ->first();

            if (!$cart) {
                $cart = Cart::create([
                    'user_id' => $user->user_id,
                    'creation_date' => now(),
                    'expiration_date' => null,
                    'active' => Cart::STATUS_ACTIVE ?? 'active',
                ]);
            }

            $existing = DB::table('book_cart')
                ->where('cart_id', $cart->cart_id)
                ->where('book_id', $data['book_id'])
                ->first();

            if ($existing) {
                DB::table('book_cart')
                    ->where('cart_id', $cart->cart_id)
                    ->where('book_id', $data['book_id'])
                    ->update([
                        'quantity' => (int) $existing->quantity + (int) $data['quantity'],
                    ]);
            } else {
                DB::table('book_cart')->insert([
                    'cart_id' => $cart->cart_id,
                    'book_id' => $data['book_id'],
                    'quantity' => $data['quantity'],
                ]);
            }
        });

        $cart = Cart::with('books')
            ->where('user_id', $user->user_id)
            ->where('active', Cart::STATUS_ACTIVE ?? 'active')
            ->latest('cart_id')
            ->firstOrFail();

        return response()->json([
            'message' => 'Book added to cart.',
            'data' => $this->formatCartResponse($cart),
        ], 201);
    }

    /**
     * Actualiza la cantidad de un libro en el carrito activo.
     */
    public function updateItem(Request $request, int $bookId): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::where('user_id', $user->user_id)
            ->where('active', Cart::STATUS_ACTIVE ?? 'active')
            ->latest('cart_id')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Active cart not found.',
            ], 404);
        }

        $exists = DB::table('book_cart')
            ->where('cart_id', $cart->cart_id)
            ->where('book_id', $bookId)
            ->exists();

        if (!$exists) {
            return response()->json([
                'message' => 'Book not found in cart.',
            ], 404);
        }

        DB::table('book_cart')
            ->where('cart_id', $cart->cart_id)
            ->where('book_id', $bookId)
            ->update([
                'quantity' => $data['quantity'],
            ]);

        $cart->load('books');

        return response()->json([
            'message' => 'Cart item updated.',
            'data' => $this->formatCartResponse($cart),
        ]);
    }

    /**
     * Elimina un libro del carrito activo.
     */
    public function deleteItem(Request $request, int $bookId): JsonResponse
    {
        $user = $request->user();

        $cart = Cart::where('user_id', $user->user_id)
            ->where('active', Cart::STATUS_ACTIVE ?? 'active')
            ->latest('cart_id')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Active cart not found.',
            ], 404);
        }

        $deleted = DB::table('book_cart')
            ->where('cart_id', $cart->cart_id)
            ->where('book_id', $bookId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Book not found in cart.',
            ], 404);
        }

        $cart->load('books');

        return response()->json([
            'message' => 'Book removed from cart.',
            'data' => $this->formatCartResponse($cart),
        ]);
    }

    /**
     * Formatea la salida del carrito para frontend.
     */
    private function formatCartResponse(Cart $cart): array
    {
        $items = $cart->books->map(function ($book) {
            $quantity = (int) $book->pivot->quantity;
            $price = (float) $book->price;
            $lineTotal = $price * $quantity;

            return [
                'book_id' => $book->book_id,
                'title' => $book->title,
                'author' => $book->author,
                'front_page' => $book->front_page,
                'format' => $book->format,
                'price' => round($price, 2),
                'quantity' => $quantity,
                'line_total' => round($lineTotal, 2),
            ];
        })->values();

        $totalQuantity = $items->sum('quantity');
        $totalAmount = $items->sum('line_total');

        return [
            'cart' => [
                'cart_id' => $cart->cart_id,
                'user_id' => $cart->user_id,
                'creation_date' => $cart->creation_date,
                'expiration_date' => $cart->expiration_date,
                'active' => $cart->active,
            ],
            'items' => $items,
            'summary' => [
                'items_count' => $items->count(),
                'total_quantity' => $totalQuantity,
                'total_amount' => round((float) $totalAmount, 2),
            ],
        ];
    }
}
