<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Fantasy',
                'description' => 'Epic fantasy, urban fantasy and magical worlds.',
            ],
            [
                'name' => 'Science Fiction',
                'description' => 'Futuristic stories, space travel and speculative technology.',
            ],
            [
                'name' => 'Mystery',
                'description' => 'Crime, detective and suspense novels.',
            ],
            [
                'name' => 'Romance',
                'description' => 'Stories focused on love and relationships.',
            ],
            [
                'name' => 'History',
                'description' => 'Historical books and historical fiction.',
            ],
            [
                'name' => 'Programming',
                'description' => 'Software development, coding and technical books.',
            ],
            [
                'name' => 'Self-Help',
                'description' => 'Personal growth, productivity and well-being.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
