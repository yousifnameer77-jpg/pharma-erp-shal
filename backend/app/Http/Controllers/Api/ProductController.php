<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'manufacturer'])
            // Single summed aggregate per product instead of an N+1 query per row.
            ->withSum('stockRecords as total_stock_sum', 'quantity_on_hand')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->query('category_id')))
            ->when($request->filled('manufacturer_id'), fn ($q) => $q->where('manufacturer_id', $request->query('manufacturer_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($q2) => $q2->where('name', 'ilike', $term)
                    ->orWhere('generic_name', 'ilike', $term)
                    ->orWhere('code', 'ilike', $term)
                    ->orWhere('barcode', 'ilike', $term));
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return response()->json(ProductResource::collection($products)->response()->getData(true));
    }

    /**
     * Products at or below their reorder point / minimum stock level —
     * fed to a "needs reordering" dashboard widget.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'manufacturer'])
            ->withSum('stockRecords as total_stock_sum', 'quantity_on_hand')
            ->where('is_active', true)
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->query('category_id')))
            ->get()
            ->filter(fn (Product $product) => $product->is_below_min_stock)
            ->values();

        return response()->json(['data' => ProductResource::collection($products)]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return response()->json(['data' => new ProductResource($product)], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'manufacturer', 'barcodes'])
            ->loadSum('stockRecords as total_stock_sum', 'quantity_on_hand');

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->update($product, $request->validated());

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->deactivate($product);

        return response()->json(['message' => 'Product deactivated successfully.']);
    }
}
