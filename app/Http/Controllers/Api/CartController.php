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

        $cart = $this->resolveActiveCartForUser($user->user_id);

        return response()->json([
            'data' => $this->buildCartResponse($cart),
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
        ]);

        $bookId = $data['book_id'];

        $alreadyPurchased = DB::table('orders')
            ->join('book_order', 'orders.order_id', '=', 'book_order.order_id')
            ->where('orders.user_id', $user->user_id)
            ->where('orders.status', 'paid')
            ->where('book_order.book_id', $bookId)
            ->exists();

        if ($alreadyPurchased) {
            return response()->json([
                'message' => 'You already own this book.',
            ], 422);
        }

        $cart = $this->resolveActiveCartForUser($user->user_id);

        if (!$cart) {
            $cart = $this->createCartForUser($user->user_id);
            $cart->load('books');
        }

        $alreadyInCart = DB::table('book_cart')
            ->where('cart_id', $cart->cart_id)
            ->where('book_id', $bookId)
            ->exists();

        if ($alreadyInCart) {
            return response()->json([
                'message' => 'This book is already in your cart.',
            ], 422);
        }

        DB::table('book_cart')->insert([
            'cart_id' => $cart->cart_id,
            'book_id' => $bookId,
            'quantity' => 1,
        ]);

        $cart = Cart::with('books')->find($cart->cart_id);

        return response()->json([
            'message' => 'Book added to cart successfully.',
            'data' => $this->buildCartResponse($cart),
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

        $cart = $this->resolveActiveCartForUser($user->user_id);

        if (!$cart) {
            return response()->json([
                'message' => 'Active cart not found.',
            ], 404);
        }

        DB::table('book_cart')
            ->where('cart_id', $cart->cart_id)
            ->where('book_id', $bookId)
            ->delete();

        $cart = Cart::with('books')->find($cart->cart_id);

        return response()->json([
            'message' => 'Book removed from cart successfully.',
            'data' => $this->buildCartResponse($cart),
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

    private function buildCartResponse($cart): array
    {
        if (!$cart) {
            return [
                'cart' => null,
                'items' => [],
                'summary' => [
                    'items_count' => 0,
                    'total_amount' => 0,
                ],
            ];
        }

        $items = $cart->books->map(function ($book) {
            return [
                'book_id' => $book->book_id,
                'title' => $book->title,
                'author' => $book->author,
                'front_page' => $book->front_page,
                'format' => $book->format,
                'price' => (float) $book->price,
                'line_total' => (float) $book->price,
            ];
        })->values();

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
                'total_amount' => $items->sum('line_total'),
            ],
        ];
    }

    private function resolveActiveCartForUser(int $userId): ?Cart
    {
        $cart = Cart::with('books')
            ->where('user_id', $userId)
            ->where('active', 'active')
            ->orderByDesc('creation_date')
            ->first();

        if (!$cart) {
            return null;
        }

        if ($this->isCartExpired($cart)) {
            $cart->update([
                'active' => 'closed',
            ]);

            return null;
        }

        return $cart;
    }

    private function isCartExpired(Cart $cart): bool
    {
        if (!$cart->expiration_date) {
            return false;
        }

        return now()->greaterThan($cart->expiration_date);
    }

    private function createCartForUser(int $userId): Cart
    {
        return Cart::create([
            'user_id' => $userId,
            'creation_date' => now(),
            'expiration_date' => now()->addDays(15),
            'active' => 'active',
        ]);
    }
}
