<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Lista pedidos del usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::with('books')
            ->where('user_id', $user->user_id)
            ->orderByDesc('order_id')
            ->get();

        $data = $orders->map(function ($order) {
            return $this->formatOrder($order);
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Muestra detalle de un pedido concreto del usuario.
     */
    public function show(Request $request, int $orderId): JsonResponse
    {
        $user = $request->user();

        $order = Order::with('books')
            ->where('order_id', $orderId)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Crea pedido desde el carrito activo (checkout simulado).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'payment_method' => ['required', 'in:card,paypal'],
            'card_holder' => ['nullable', 'string', 'max:255'],
            'card_number' => ['nullable', 'string', 'max:25'],
            'expiry_date' => ['nullable', 'string', 'max:10'],
            'cvv' => ['nullable', 'string', 'max:4'],
            'paypal_email' => ['nullable', 'email', 'max:255'],
        ]);

        if ($data['payment_method'] === 'card') {
            if (
                empty($data['card_holder']) ||
                empty($data['card_number']) ||
                empty($data['expiry_date']) ||
                empty($data['cvv'])
            ) {
                return response()->json([
                    'message' => 'Card payment data is incomplete.',
                ], 422);
            }
        }

        if ($data['payment_method'] === 'paypal') {
            if (empty($data['paypal_email'])) {
                return response()->json([
                    'message' => 'PayPal email is required.',
                ], 422);
            }
        }

        $cart = Cart::with('books')
            ->where('user_id', $user->user_id)
            ->where('active', 'active')
            ->first();

        if (!$cart || $cart->books->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty.',
            ], 422);
        }

        $totalAmount = $cart->books->sum(function ($book) {
            return (float) $book->price;
        });

        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $user->user_id,
                'order_date' => now(),
                'total_amount' => $totalAmount,
                'status' => 'paid',
            ]);

            foreach ($cart->books as $book) {
                DB::table('book_order')->insert([
                    'order_id' => $order->order_id,
                    'book_id' => $book->book_id,
                    'unit_price' => $book->price,
                ]);
            }

            DB::table('book_cart')
                ->where('cart_id', $cart->cart_id)
                ->delete();

            $cart->update([
                'active' => 'closed',
            ]);

            $transactionReference = 'SIM-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));

            DB::commit();

            $order->load('books');

            return response()->json([
                'message' => 'Order created successfully.',
                'data' => [
                    'payment' => [
                        'simulated' => true,
                        'method' => $data['payment_method'],
                        'status' => 'paid',
                        'transaction_reference' => $transactionReference,
                    ],
                    'order' => [
                        'order_id' => $order->order_id,
                        'user_id' => $order->user_id,
                        'order_date' => $order->order_date,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                    ],
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'The order could not be processed.',
            ], 500);
        }
    }

    /**
     * Formatea un pedido con líneas para frontend.
     */
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
                'format' => $book->format,
                'unit_price' => round($unitPrice, 2),
            ];
        })->values();

        return [
            'order_id' => $order->order_id,
            'user_id' => $order->user_id,
            'order_date' => $order->order_date,
            'total_amount' => (float) $order->total_amount,
            'status' => $order->status,
            'items' => $items,
        ];
    }
}
