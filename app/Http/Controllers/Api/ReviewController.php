<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function indexByBook(int $bookId): JsonResponse
    {
        $bookExists = Book::where('book_id', $bookId)->exists();

        if (!$bookExists) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $reviews = Review::with(['user'])
            ->where('book_id', $bookId)
            ->orderByDesc('review_id')
            ->get()
            ->map(function ($review) {
                return $this->formatReview($review);
            });

        return response()->json([
            'data' => $reviews,
        ]);
    }

    public function store(Request $request, int $bookId): JsonResponse
    {
        $user = $request->user();

        $book = Book::find($bookId);
        if (!$book) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:255'],
        ]);

        // Verifica compra previa (pedido pagado)
        $hasPurchased = DB::table('orders')
            ->join('book_order', 'orders.order_id', '=', 'book_order.order_id')
            ->where('orders.user_id', $user->user_id)
            ->where('orders.status', 'paid')
            ->where('book_order.book_id', $bookId)
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'message' => 'You can only review books you have purchased.',
            ], 403);
        }

        // Evitar reseñas duplicadas (1 por usuario/libro)
        $existingReview = Review::where('user_id', $user->user_id)
            ->where('book_id', $bookId)
            ->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'You have already reviewed this book.',
            ], 409);
        }

        $review = Review::create([
            'user_id' => $user->user_id,
            'book_id' => $bookId,
            'points' => $data['points'],
            'comment' => $data['comment'],
            'date' => now(),
        ]);

        $review->load('user');

        return response()->json([
            'message' => 'Review created successfully.',
            'data' => $this->formatReview($review),
        ], 201);
    }

    public function update(Request $request, int $reviewId): JsonResponse
    {
        $user = $request->user();

        $review = Review::with('user')->find($reviewId);
        if (!$review) {
            return response()->json([
                'message' => 'Review not found.',
            ], 404);
        }

        if ((int) $review->user_id !== (int) $user->user_id) {
            return response()->json([
                'message' => 'You are not allowed to update this review.',
            ], 403);
        }

        $data = $request->validate([
            'points' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'string', 'max:255'],
        ]);

        $review->fill($data);
        $review->save();

        $review->load('user');

        return response()->json([
            'message' => 'Review updated successfully.',
            'data' => $this->formatReview($review),
        ]);
    }

    public function destroy(Request $request, int $reviewId): JsonResponse
    {
        $user = $request->user();

        $review = Review::find($reviewId);
        if (!$review) {
            return response()->json([
                'message' => 'Review not found.',
            ], 404);
        }

        if ((int) $review->user_id !== (int) $user->user_id) {
            return response()->json([
                'message' => 'You are not allowed to delete this review.',
            ], 403);
        }

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully.',
        ]);
    }

    private function formatReview(Review $review): array
    {
        return [
            'review_id' => $review->review_id,
            'book_id' => $review->book_id,
            'user_id' => $review->user_id,
            'points' => (int) $review->points,
            'comment' => $review->comment,
            'date' => $review->date,
            'user' => $review->relationLoaded('user') && $review->user ? [
                'user_id' => $review->user->user_id,
                'name' => $review->user->name,
            ] : null,
        ];
    }
}
