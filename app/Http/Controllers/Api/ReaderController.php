<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                    "This is a simulated reader page for demonstration purposes in Júpiter.",
            ],
            [
                'page' => 2,
                'content' => "Chapter 2\n\n" .
                    "The story continues with more reading content adapted for the digital reader demo.\n\n" .
                    "Book: {$book->title}\nAuthor: {$book->author}",
            ],
            [
                'page' => 3,
                'content' => "Chapter 3\n\n" .
                    "This final sample page shows how the reading module can paginate book content " .
                    "and restrict access only to users who purchased the book.",
            ],
        ];
    }
}
