<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeCheckoutController extends Controller
{
    public function createSession(Request $request): JsonResponse
    {
        $user = $request->user();

        $cart = Cart::with('books')
            ->where('user_id', $user->user_id)
            ->where('active', 'active')
            ->first();

        if (!$cart || ($cart->expiration_date && now()->greaterThan($cart->expiration_date)) || $cart->books->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty or has expired.',
            ], 422);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $lineItems = $cart->books->map(function ($book) {
            return [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $book->title,
                        'description' => $book->author,
                    ],
                    'unit_amount' => (int) round(((float) $book->price) * 100),
                ],
                'quantity' => 1,
            ];
        })->values()->all();

        $session = Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'success_url' => rtrim(env('FRONTEND_URL'), '/') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim(env('FRONTEND_URL'), '/') . '/checkout/cancel',
            'metadata' => [
                'user_id' => (string) $user->user_id,
                'cart_id' => (string) $cart->cart_id,
            ],
        ]);

        return response()->json([
            'url' => $session->url,
        ]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->server('HTTP_STRIPE_SIGNATURE');
        $secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            if (($session->payment_status ?? null) !== 'paid') {
                return response()->json(['received' => true]);
            }

            $userId = (int) ($session->metadata->user_id ?? 0);
            $cartId = (int) ($session->metadata->cart_id ?? 0);

            $cart = Cart::with('books')
                ->where('cart_id', $cartId)
                ->where('user_id', $userId)
                ->where('active', 'active')
                ->first();

            if ($cart && $cart->books->isNotEmpty()) {
                $existingOrder = Order::where('user_id', $userId)
                    ->where('status', 'paid')
                    ->whereDate('order_date', now()->toDateString())
                    ->where('total_amount', (float) ($session->amount_total / 100))
                    ->latest('order_id')
                    ->first();

                if (!$existingOrder) {
                    DB::beginTransaction();

                    try {
                        $order = Order::create([
                            'user_id' => $userId,
                            'order_date' => now(),
                            'total_amount' => (float) ($session->amount_total / 100),
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

                        DB::commit();
                    } catch (\Throwable $e) {
                        DB::rollBack();

                        return response()->json([
                            'message' => 'Webhook order processing failed.',
                        ], 500);
                    }
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
