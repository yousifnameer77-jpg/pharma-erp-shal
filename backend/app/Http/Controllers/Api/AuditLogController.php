<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,username,full_name')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', '%' . $request->query('auditable_type') . '%');
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->query('date_from') . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->query('date_to') . ' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('auditable_type', 'like', "%{$search}%")
                  ->orWhere('auditable_id', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) ($request->query('per_page', 25)), 100);
        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json([
            'data' => $auditLog->load('user:id,username,full_name'),
        ]);
    }
}

