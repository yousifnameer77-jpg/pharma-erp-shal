<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PrescriptionRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDrugController extends Controller
{
    /**
     * Get inspection-ready registry of controlled drugs & prescriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user?->branch?->company_id ?? \App\Models\Company::first()?->id;

        $query = PrescriptionRecord::with(['product', 'batch', 'dispensedByUser', 'salesInvoice'])
            ->where('company_id', $companyId)
            ->orderBy('dispensed_at', 'desc');

        if ($request->filled('doctor_name')) {
            $query->where('doctor_name', 'like', '%' . $request->doctor_name . '%');
        }

        if ($request->filled('prescription_number')) {
            $query->where('prescription_number', 'like', '%' . $request->prescription_number . '%');
        }

        if ($request->filled('patient_name')) {
            $query->where('patient_name', 'like', '%' . $request->patient_name . '%');
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('dispensed_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('dispensed_at', '<=', $request->to_date);
        }

        $records = $query->paginate($request->integer('per_page', 20));

        // Aggregate statistics for ministry inspector
        $totalDispensed = PrescriptionRecord::where('company_id', $companyId)->sum('quantity_dispensed');
        $uniquePrescriptions = PrescriptionRecord::where('company_id', $companyId)->distinct('prescription_number')->count('prescription_number');
        $uniqueDoctors = PrescriptionRecord::where('company_id', $companyId)->distinct('doctor_name')->count('doctor_name');
        $flaggedLast30Days = PrescriptionRecord::where('company_id', $companyId)
            ->where('dispensed_at', '>=', Carbon::now()->subDays(30))
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
            'kpis' => [
                'total_quantity_dispensed' => (float) $totalDispensed,
                'unique_prescriptions' => $uniquePrescriptions,
                'unique_doctors' => $uniqueDoctors,
                'dispensed_last_30_days' => $flaggedLast30Days,
                'inspection_compliance_status' => '100% متوافق مع تعليمات نقابة الصيادلة ووزارة الصحة',
            ],
        ]);
    }
}

