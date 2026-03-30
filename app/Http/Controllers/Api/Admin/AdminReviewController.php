<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class AdminReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $reviews = Review::with([
            'user:user_id,name,email',
            'book:book_id,title,author'
        ])
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'data' => $reviews,
        ]);
    }

    public function destroy(int $reviewId): JsonResponse
    {
        $review = Review::find($reviewId);

        if (!$review) {
            return response()->json([
                'message' => 'Review not found.',
            ], 404);
        }

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully.',
        ]);
    }
}
