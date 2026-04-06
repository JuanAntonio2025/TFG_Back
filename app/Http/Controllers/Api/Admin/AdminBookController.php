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
            ->get()
            ->map(fn (Book $book) => $this->transformBook($book));

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
            'data' => $this->transformBook($book),
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
            'book_file' => ['nullable', 'file', 'mimes:pdf,epub', 'max:51200'],
            'format' => ['required', 'in:PDF,EPUB'],
            'available' => ['required', 'in:available,unavailable'],
            'featured' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,category_id'],
        ]);

        if ($request->hasFile('book_file')) {
            $extension = strtolower($request->file('book_file')->getClientOriginalExtension());
            $selectedFormat = strtoupper($request->input('format'));

            if (
                ($selectedFormat === 'PDF' && $extension !== 'pdf') ||
                ($selectedFormat === 'EPUB' && $extension !== 'epub')
            ) {
                return response()->json([
                    'message' => 'The uploaded file does not match the selected format.',
                ], 422);
            }
        }

        $categoryIds = $request->input('category_ids', []);

        if (!is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }

        $data['featured'] = filter_var($request->input('featured', false), FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('front_page')) {
            $coverPath = $request->file('front_page')->store('covers', 'jupiter_covers');
            $data['front_page'] = $coverPath;
        } else {
            $data['front_page'] = null;
        }

        if ($request->hasFile('book_file')) {
            $bookPath = $request->file('book_file')->store('books', 'jupiter_books');
            $data['file_path'] = $bookPath;
        } else {
            $data['file_path'] = null;
        }

        unset($data['category_ids']);
        unset($data['book_file']);

        $book = Book::create($data);

        if (!empty($categoryIds)) {
            $book->categories()->sync($categoryIds);
        }

        $book->load('categories');

        return response()->json([
            'message' => 'Book created successfully.',
            'data' => $this->transformBook($book),
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
            'book_file' => ['nullable', 'file', 'mimes:pdf,epub', 'max:51200'],
            'format' => ['sometimes', 'in:PDF,EPUB'],
            'available' => ['sometimes', 'in:available,unavailable'],
            'featured' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,category_id'],
        ]);

        if ($request->hasFile('book_file')) {
            $selectedFormat = strtoupper($request->input('format', $book->format));
            $extension = strtolower($request->file('book_file')->getClientOriginalExtension());

            if (
                ($selectedFormat === 'PDF' && $extension !== 'pdf') ||
                ($selectedFormat === 'EPUB' && $extension !== 'epub')
            ) {
                return response()->json([
                    'message' => 'The uploaded file does not match the selected format.',
                ], 422);
            }
        }

        $categoryIds = $request->input('category_ids', null);

        if ($categoryIds !== null && !is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }

        if ($request->has('featured')) {
            $data['featured'] = filter_var($request->input('featured'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('front_page')) {
            if ($book->front_page) {
                Storage::disk('jupiter_covers')->delete($book->front_page);
            }

            $coverPath = $request->file('front_page')->store('covers', 'jupiter_covers');
            $data['front_page'] = $coverPath;
        }

        if ($request->hasFile('book_file')) {
            if ($book->file_path) {
                Storage::disk('jupiter_books')->delete($book->file_path);
            }

            $bookPath = $request->file('book_file')->store('books', 'jupiter_books');
            $data['file_path'] = $bookPath;
        }

        unset($data['category_ids']);
        unset($data['book_file']);

        $book->fill($data);
        $book->save();

        if (is_array($categoryIds)) {
            $book->categories()->sync($categoryIds);
        }

        $book->load('categories');

        return response()->json([
            'message' => 'Book updated successfully.',
            'data' => $this->transformBook($book),
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
            Storage::disk('jupiter_covers')->delete($book->front_page);
        }

        if ($book->file_path) {
            Storage::disk('jupiter_books')->delete($book->file_path);
        }

        $book->delete();

        return response()->json([
            'message' => 'Book deleted successfully.',
        ]);
    }

    private function transformBook(Book $book): array
    {
        $data = $book->toArray();

        $data['front_page_url'] = $book->front_page
            ? Storage::disk('jupiter_covers')->url($book->front_page)
            : null;

        return $data;
    }
}
