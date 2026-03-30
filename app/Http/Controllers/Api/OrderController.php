<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Para demo, permitimos elegir méthodo pero no procesamos pago real.
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:card,paypal'],
        ]);

        $result = DB::transaction(function () use ($user, $data) {
            $cart = Cart::with('books')
                ->where('user_id', $user->user_id)
                ->where('active', Cart::STATUS_ACTIVE ?? 'active')
                ->latest('cart_id')
                ->lockForUpdate()
                ->first();

            if (!$cart) {
                return [
                    'error' => response()->json([
                        'message' => 'Active cart not found.',
                    ], 404),
                ];
            }

            if ($cart->books->isEmpty()) {
                return [
                    'error' => response()->json([
                        'message' => 'Cart is empty.',
                    ], 422),
                ];
            }

            // Calculamos total
            $totalAmount = 0.0;
            foreach ($cart->books as $book) {
                $totalAmount = $cart->books->sum('price');
            }

            // Pedido "realista pero simulado":
            // para demo lo marcamos como paid directamente.
            $order = Order::create([
                'user_id' => $user->user_id,
                'order_date' => now(),
                'total_amount' => round($totalAmount, 2),
                'status' => Order::STATUS_PAID ?? 'paid',
            ]);

            // Copiamos líneas a book_order
            foreach ($cart->books as $book) {
                $unitPrice = (float) $book->price;

                // Como book_order no tiene quantity en tu diseño, insertamos una línea por libro.
                // El "qty" ya se ha usado para calcular el total, pero no se almacena en pedido.
                // Si en el futuro quieres soportar cantidades en pedidos, añade quantity a book_order.
                DB::table('book_order')->insert([
                    'order_id' => $order->order_id,
                    'book_id' => $book->book_id,
                    'unit_price' => round($unitPrice, 2),
                ]);

                // Si quantity > 1 y tu PK es (order_id, book_id), no puedes repetir libro.
                // Esto implica que conceptualmente en pedidos cada libro aparece una sola vez.
                // Para ebooks tiene sentido (normalmente no compras el mismo ebook varias veces).
                // Si quieres bloquear qty>1 en checkout, lo hacemos luego.
            }

            // Cerramos carrito
            $cart->active = Cart::STATUS_CLOSED ?? 'closed';
            $cart->expiration_date = now();
            $cart->save();

            // Recargamos pedido con libros
            $order->load('books');

            return [
                'order' => $order,
                'payment_method' => $data['payment_method'] ?? null,
            ];
        });

        if (isset($result['error'])) {
            return $result['error'];
        }

        return response()->json([
            'message' => 'Order created successfully (simulated payment).',
            'data' => [
                'payment' => [
                    'simulated' => true,
                    'method' => $result['payment_method'],
                    'status' => 'paid',
                ],
                'order' => $this->formatOrder($result['order']),
            ],
        ], 201);
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
