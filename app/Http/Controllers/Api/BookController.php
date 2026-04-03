<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Book::query()
            ->with(['categories'])
            ->where('available', 'available');

        // Búsqueda por título/autor
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }

        // Filtro por categoría
        if ($request->filled('category_id')) {
            $categoryId = (int) $request->query('category_id');

            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.category_id', $categoryId);
            });
        }

        //Destacados
        if ($request->has('featured')) {
            $query->where('featured', filter_var($request->featured, FILTER_VALIDATE_BOOLEAN))->limit(4);
        }

        // Orden opcional
        $sort = $request->query('sort', 'title_asc');

        match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'title_desc' => $query->orderBy('title', 'desc'),
            default      => $query->orderBy('title', 'asc'),
        };

        $perPage = min((int) $request->query('per_page', 12), 50);
        if ($perPage <= 0) {
            $perPage = 12;
        }

        $books = $query->paginate($perPage);

        return response()->json($books);
    }

    public function show(int $bookId): JsonResponse
    {
        $book = Book::with([
            'categories',
            'reviews.user',
        ])->findOrFail($bookId);

        $avgPoints = $book->reviews()->avg('points');
        $totalReviews = $book->reviews()->count();

        return response()->json([
            'data' => [
                'book' => $book,
                'reviews_summary' => [
                    'avg_points' => $avgPoints ? round((float) $avgPoints, 2) : null,
                    'total_reviews' => $totalReviews,
                ],
            ],
        ]);
    }
}
