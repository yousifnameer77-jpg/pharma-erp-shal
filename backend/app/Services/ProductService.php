<?php

namespace App\Services;

use App\Models\Product;

class ProductService
{
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh();
    }

    /**
     * Deactivated, not deleted: a sold product is referenced forever by
     * sales_invoice_lines and stock_movements once the Sales module exists.
     */
    public function deactivate(Product $product): void
    {
        $product->update(['is_active' => false]);
    }
}
