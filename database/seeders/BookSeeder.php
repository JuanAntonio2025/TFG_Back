<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $books = [
            [
                'title' => 'A Game of Thrones',
                'author' => 'George R. R. Martin',
                'description' => 'The first book in A Song of Ice and Fire.',
                'price' => 14.99,
                'front_page' => 'covers/game-of-thrones.jpg',
                'format' => 'EPUB',
                'available' => 'available',
                'categories' => ['Fantasy'],
            ],
            [
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'description' => 'Classic science fiction novel set on Arrakis.',
                'price' => 12.50,
                'front_page' => 'covers/dune.jpg',
                'format' => 'PDF',
                'available' => 'available',
                'categories' => ['Science Fiction'],
            ],
            [
                'title' => 'The Hobbit',
                'author' => 'J. R. R. Tolkien',
                'description' => 'Bilbo Baggins begins an unexpected journey.',
                'price' => 10.99,
                'front_page' => 'covers/the-hobbit.jpg',
                'format' => 'EPUB',
                'available' => 'available',
                'categories' => ['Fantasy'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'description' => 'A handbook of agile software craftsmanship.',
                'price' => 29.90,
                'front_page' => 'covers/clean-code.jpg',
                'format' => 'PDF',
                'available' => 'available',
                'categories' => ['Programming'],
            ],
            [
                'title' => 'Atomic Habits',
                'author' => 'James Clear',
                'description' => 'Practical strategies to build good habits and break bad ones.',
                'price' => 16.75,
                'front_page' => 'covers/atomic-habits.jpg',
                'format' => 'EPUB',
                'available' => 'available',
                'categories' => ['Self-Help'],
            ],
            [
                'title' => 'The Da Vinci Code',
                'author' => 'Dan Brown',
                'description' => 'Mystery thriller with symbols and conspiracies.',
                'price' => 11.20,
                'front_page' => 'covers/da-vinci-code.jpg',
                'format' => 'PDF',
                'available' => 'available',
                'categories' => ['Mystery'],
            ],
            [
                'title' => 'Pride and Prejudice',
                'author' => 'Jane Austen',
                'description' => 'Classic romance novel.',
                'price' => 9.50,
                'front_page' => 'covers/pride-and-prejudice.jpg',
                'format' => 'EPUB',
                'available' => 'available',
                'categories' => ['Romance', 'History'],
            ],
        ];

        foreach ($books as $bookData) {
            $categoryNames = $bookData['categories'];
            unset($bookData['categories']);

            $book = Book::updateOrCreate(
                ['title' => $bookData['title'], 'author' => $bookData['author']],
                $bookData
            );

            $categoryIds = Category::whereIn('name', $categoryNames)
                ->pluck('category_id')
                ->toArray();

            if (!empty($categoryIds)) {
                $book->categories()->syncWithoutDetaching($categoryIds);
            }
        }
    }
}
