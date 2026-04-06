<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReaderController extends Controller
{
    public function show(Request $request, int $bookId): JsonResponse
    {
        $user = $request->user();

        $book = Book::find($bookId);

        if (!$book) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $hasPurchased = DB::table('orders')
            ->join('book_order', 'orders.order_id', '=', 'book_order.order_id')
            ->where('orders.user_id', $user->user_id)
            ->where('orders.status', 'paid')
            ->where('book_order.book_id', $bookId)
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'message' => 'You are not allowed to read this book.',
            ], 403);
        }

        $pages = $this->generateBookPages($book);

        return response()->json([
            'data' => [
                'book_id' => $book->book_id,
                'title' => $book->title,
                'author' => $book->author,
                'format' => $book->format,
                'pages' => $pages,
            ],
        ]);
    }

    private function generateBookPages(Book $book): array
    {
        $baseText = $book->description ?: 'This is sample reading content for the selected book.';

        return [
            [
                'page' => 1,
                'content' => "Chapter 1\n\n" . $baseText . "\n\n" .
                    "This is sample reader content for books without a rendered file.",
            ],
            [
                'page' => 2,
                'content' => "Chapter 2\n\n" .
                    "Book: {$book->title}\nAuthor: {$book->author}",
            ],
        ];
    }

    public function file(Request $request, int $bookId)
    {
        $user = $request->user();

        $book = Book::find($bookId);

        if (!$book) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $hasPurchased = DB::table('orders')
            ->join('book_order', 'orders.order_id', '=', 'book_order.order_id')
            ->where('orders.user_id', $user->user_id)
            ->where('orders.status', 'paid')
            ->where('book_order.book_id', $bookId)
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'message' => 'You are not allowed to access this file.',
            ], 403);
        }

        if (!$book->file_path || !Storage::disk('jupiter_books')->exists($book->file_path)) {
            return response()->json([
                'message' => 'Book file not found.',
            ], 404);
        }

        $mimeType = match (strtoupper($book->format)) {
            'PDF' => 'application/pdf',
            'EPUB' => 'application/epub+zip',
            default => 'application/octet-stream',
        };

        $stream = Storage::disk('jupiter_books')->readStream($book->file_path);

        if ($stream === false) {
            return response()->json([
                'message' => 'Could not open the book file.',
            ], 500);
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($book->file_path) . '"',
        ]);
    }
}
