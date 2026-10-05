<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->withCount('products')
            ->with('parent')
            ->when($request->filled('parent_id'), fn ($q) => $q->where('parent_id', $request->query('parent_id')))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(['data' => new CategoryResource($category->loadCount('products')->load('parent'))]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(['data' => new CategoryResource($category->fresh())]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
