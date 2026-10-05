<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only: current on-hand quantities per (warehouse, batch). Stock rows
 * are only ever written by StockMovementService, never through this
 * controller — see StockMovementController for anything that changes stock.
 */
class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $stock = Stock::query()
            ->with(['warehouse', 'batch.product'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->query('batch_id')))
            ->when($request->filled('product_id'), function ($q) use ($request) {
                $q->whereHas('batch', fn ($b) => $b->where('product_id', $request->query('product_id')));
            })
            ->when($request->boolean('only_available'), fn ($q) => $q->whereColumn('quantity_on_hand', '>', 'reserved_quantity'))
            ->orderBy('updated_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(StockResource::collection($stock)->response()->getData(true));
    }
}
