<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'front_page' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'format' => ['required', 'in:PDF,EPUB'],
            'available' => ['required', 'in:available,unavailable'],
            'featured' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,category_id'],
        ]);

        $categoryIds = $request->input('category_ids', []);

        if (!is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }

        $data['featured'] = filter_var($request->input('featured', false), FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('front_page')) {
            $path = $request->file('front_page')->store('covers', 'public');
            $data['front_page'] = $path;
        } else {
            $data['front_page'] = null;
        }

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
            'front_page' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'format' => ['sometimes', 'in:PDF,EPUB'],
            'available' => ['sometimes', 'in:available,unavailable'],
            'featured' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,category_id'],
        ]);

        $categoryIds = $request->input('category_ids', null);

        if ($categoryIds !== null && !is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }

        if ($request->has('featured')) {
            $data['featured'] = filter_var($request->input('featured'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('front_page')) {
            if ($book->front_page) {
                Storage::disk('public')->delete($book->front_page);
            }

            $path = $request->file('front_page')->store('covers', 'public');
            $data['front_page'] = $path;
        }

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

        if ($book->front_page) {
            Storage::disk('public')->delete($book->front_page);
        }

        $book->delete();

        return response()->json([
            'message' => 'Book deleted successfully.',
        ]);
    }
}
