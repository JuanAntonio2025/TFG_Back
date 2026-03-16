<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Category::orderBy('name')->get(),
        ]);
    }

    public function show(int $categoryId): JsonResponse
    {
        $category = Category::with('books')->find($categoryId);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], 404);
        }

        return response()->json([
            'data' => $category,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $category = Category::create($data);

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, int $categoryId): JsonResponse
    {
        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
        ]);

        $category->fill($data);
        $category->save();

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => $category,
        ]);
    }

    public function destroy(int $categoryId): JsonResponse
    {
        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json([
                'message' => 'Category not found.',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
