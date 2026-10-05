<?php

namespace App\Services;

use App\Exceptions\Purchasing\InvalidStatusTransitionException;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * First step of the workflow: Purchase Request -> Purchase Order -> Receive
 * Goods -> Invoice -> Supplier Balance. A request carries no supplier or
 * price — just "what do we need" — and is turned into a priced PurchaseOrder
 * by PurchaseOrderService::createFromRequest() once approved.
 */
class PurchaseRequestService
{
    public function __construct(private readonly DocumentSequenceService $sequences)
    {
    }

    /**
     * @param  array{company_id: string, branch_id: string, warehouse_id: string, notes?: ?string, items: array<int, array{product_id: string, quantity: float, notes?: ?string}>}  $data
     */
    public function create(array $data, User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $user) {
            $request = PurchaseRequest::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'request_number' => $this->sequences->next($data['company_id'], 'purchase_request', 'PR'),
                'status' => 'draft',
                'requested_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $request->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $request->load('items');
        });
    }

    /**
     * @param  array{notes?: ?string, items?: array<int, array{product_id: string, quantity: float, notes?: ?string}>}  $data
     */
    public function update(PurchaseRequest $request, array $data): PurchaseRequest
    {
        $this->guard($request, 'draft', 'update');

        return DB::transaction(function () use ($request, $data) {
            $request->update(['notes' => $data['notes'] ?? $request->notes]);

            if (array_key_exists('items', $data)) {
                $request->items()->delete();
                foreach ($data['items'] as $item) {
                    $request->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            return $request->fresh('items');
        });
    }

    public function submit(PurchaseRequest $request): PurchaseRequest
    {
        $this->guard($request, 'draft', 'submitted');
        $request->update(['status' => 'submitted']);

        return $request->fresh();
    }

    public function approve(PurchaseRequest $request, User $user): PurchaseRequest
    {
        $this->guard($request, 'submitted', 'approved');
        $request->update(['status' => 'approved', 'approved_by' => $user->id]);

        return $request->fresh();
    }

    public function reject(PurchaseRequest $request, User $user): PurchaseRequest
    {
        $this->guard($request, 'submitted', 'rejected');
        $request->update(['status' => 'rejected', 'approved_by' => $user->id]);

        return $request->fresh();
    }

    public function cancel(PurchaseRequest $request): PurchaseRequest
    {
        if (! in_array($request->status, ['draft', 'submitted', 'approved'], true)) {
            throw new InvalidStatusTransitionException('purchase_request', $request->id, $request->status, 'cancelled');
        }

        $request->update(['status' => 'cancelled']);

        return $request->fresh();
    }

    private function guard(PurchaseRequest $request, string $expected, string $to): void
    {
        if ($request->status !== $expected) {
            throw new InvalidStatusTransitionException('purchase_request', $request->id, $request->status, $to);
        }
    }
}
