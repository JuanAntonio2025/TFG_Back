<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookController extends Controller
{
    public function index(): JsonResponse
    {
        $books = Book::with('categories')
            ->orderByDesc('book_id')
            ->get();

        return response()->json([
            'data' => $books,
        ]);
    }

    public function show(int $bookId): JsonResponse
    {
        $book = Book::with(['categories', 'reviews.user'])->find($bookId);

        if (!$book) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        return response()->json([
            'data' => $book,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'front_page' => ['nullable', 'string', 'max:255'],
            'format' => ['required', 'in:PDF,EPUB'],
            'available' => ['required', 'in:available,unavailable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,category_id'],
        ]);

        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $book = Book::create($data);

        if (!empty($categoryIds)) {
            $book->categories()->sync($categoryIds);
        }

        $book->load('categories');

        return response()->json([
            'message' => 'Book created successfully.',
            'data' => $book,
        ], 201);
    }

    public function update(Request $request, int $bookId): JsonResponse
    {
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'author' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'front_page' => ['nullable', 'string', 'max:255'],
            'format' => ['sometimes', 'in:PDF,EPUB'],
            'available' => ['sometimes', 'in:available,unavailable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,category_id'],
        ]);

        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);

        $book->fill($data);
        $book->save();

        if (is_array($categoryIds)) {
            $book->categories()->sync($categoryIds);
        }

        $book->load('categories');

        return response()->json([
            'message' => 'Book updated successfully.',
            'data' => $book,
        ]);
    }

    public function destroy(int $bookId): JsonResponse
    {
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $book->delete();

        return response()->json([
            'message' => 'Book deleted successfully.',
        ]);
    }
}
