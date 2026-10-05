"use client";

import { useState } from "react";
import { Eye, Search, Filter, User, Globe } from "lucide-react";
import { PageHeader } from "@/components/ui/page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Spinner } from "@/components/ui/spinner";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination } from "@/components/ui/pagination";
import { usePaginatedResource } from "@/lib/hooks";

interface AuditLogItem {
  id: string;
  user_id: string | null;
  action: string;
  auditable_type: string | null;
  auditable_id: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  user: {
    id: string;
    username: string;
    full_name: string;
  } | null;
}

export default function AuditLogsPage() {
  const [search, setSearch] = useState("");
  const [actionFilter, setActionFilter] = useState("");
  const [selectedLog, setSelectedLog] = useState<AuditLogItem | null>(null);

  const { data: logs, meta, loading, error, page, setPage, refetch } = usePaginatedResource<AuditLogItem>(
    "/v1/audit-logs",
    {
      search: search || undefined,
      action: actionFilter || undefined,
    }
  );

  const getActionBadgeTone = (action: string): "emerald" | "blue" | "red" | "amber" | "violet" | "slate" => {
    switch (action.toLowerCase()) {
      case "created":
      case "login":
        return "emerald";
      case "updated":
        return "blue";
      case "deleted":
      case "cancelled":
        return "red";
      case "quarantined":
        return "amber";
      case "posted":
        return "violet";
      default:
        return "slate";
    }
  };

  const formatEntityName = (type: string | null) => {
    if (!type) return "—";
    const parts = type.split("\\");
    return parts[parts.length - 1];
  };

  return (
    <PermissionGate permission={PERMISSIONS.usersView}>
      <div className="flex flex-col gap-6">
        <PageHeader
          title="سجل الرقابة والتدقيق الأمني (Audit Logs)"
          description="تتبع فوري ودقيق لجميع العمليات الحساسة، التعديلات، ترحيل الفواتير، ودخول المستخدمين لضمان الأمان والشفافية."
        />

        {/* Filter Bar */}
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-card">
          <div className="flex flex-1 flex-wrap items-center gap-3">
            <div className="relative min-w-[240px] flex-1 sm:max-w-xs">
              <Search className="absolute right-3 top-2.5 h-4 w-4 text-slate-400" />
              <input
                type="text"
                placeholder="بحث في الإجراء، الكيان، الـ IP..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full rounded-lg border border-slate-300 pr-9 pl-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
              />
            </div>

            <div className="flex items-center gap-2">
              <Filter className="h-4 w-4 text-slate-400" />
              <select
                value={actionFilter}
                onChange={(e) => setActionFilter(e.target.value)}
                className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
              >
                <option value="">جميع الإجراءات (All Actions)</option>
                <option value="login">تسجيل دخول (Login)</option>
                <option value="created">إنشاء (Created)</option>
                <option value="updated">تعديل (Updated)</option>
                <option value="deleted">حذف (Deleted)</option>
                <option value="posted">ترحيل (Posted)</option>
                <option value="quarantined">عزل للحجر (Quarantined)</option>
                <option value="created_from_reorder">إعادة طلب (Reorder)</option>
              </select>
            </div>
          </div>

          <Button variant="outline" size="sm" onClick={() => refetch()} disabled={loading}>
            تحديث السجل
          </Button>
        </div>

        {/* Table */}
        {loading ? (
          <div className="flex h-48 items-center justify-center">
            <Spinner className="h-8 w-8" />
          </div>
        ) : error ? (
          <div className="rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</div>
        ) : logs.length === 0 ? (
          <EmptyState
            title="لا توجد حركات مسجلة"
            description="لم يتم العثور على أي سجلات تدقيق تطابق معايير البحث المحددة."
          />
        ) : (
          <div className="flex flex-col gap-3">
            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-card">
              <table className="min-w-full divide-y divide-slate-200 text-right text-sm">
                <thead className="bg-slate-50 text-xs font-semibold text-slate-600">
                  <tr>
                    <th className="px-4 py-3">الوقت والتاريخ</th>
                    <th className="px-4 py-3">المستخدم</th>
                    <th className="px-4 py-3">نوع الإجراء</th>
                    <th className="px-4 py-3">الكيان / المستند</th>
                    <th className="px-4 py-3">عنوان IP</th>
                    <th className="px-4 py-3 text-center">التفاصيل</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {logs.map((log) => (
                    <tr key={log.id} className="hover:bg-slate-50/70">
                      <td className="px-4 py-3 text-xs text-slate-600 font-mono">
                        {new Date(log.created_at).toLocaleString("ar-IQ")}
                      </td>
                      <td className="px-4 py-3 font-medium text-slate-900">
                        {log.user ? (
                          <div className="flex items-center gap-1.5">
                            <User className="h-3.5 w-3.5 text-slate-400" />
                            <span>{log.user.full_name}</span>
                            <span className="text-xs text-slate-400">({log.user.username})</span>
                          </div>
                        ) : (
                          <span className="text-xs text-slate-400">النظام التلقائي</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <Badge tone={getActionBadgeTone(log.action)} className="capitalize font-mono">
                          {log.action}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">
                        <span className="font-semibold text-slate-800">
                          {formatEntityName(log.auditable_type)}
                        </span>
                        {log.auditable_id && (
                          <span className="mr-1.5 font-mono text-xs text-slate-400">
                            #{log.auditable_id.slice(0, 8)}
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-xs text-slate-500 font-mono">
                        <span className="inline-flex items-center gap-1">
                          <Globe className="h-3 w-3 text-slate-400" />
                          {log.ip_address ?? "127.0.0.1"}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-center">
                        <Button
                          size="sm"
                          variant="outline"
                          className="text-xs"
                          onClick={() => setSelectedLog(log)}
                        >
                          <Eye className="ml-1 h-3.5 w-3.5" />
                          عرض التغييرات
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {meta && (
              <Pagination
                page={page}
                totalPages={meta.last_page}
                onPageChange={setPage}
              />
            )}
          </div>
        )}

        {/* Diff Modal */}
        {selectedLog && (
          <Modal
            open={!!selectedLog}
            onClose={() => setSelectedLog(null)}
            title={`تفاصيل العملية: ${selectedLog.action} (${formatEntityName(selectedLog.auditable_type)})`}
          >
            <div className="flex flex-col gap-4 text-right">
              <div className="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                <div>
                  <span className="font-medium text-slate-500">المستخدم:</span>{" "}
                  <span className="text-slate-900 font-semibold">{selectedLog.user?.full_name ?? "النظام"}</span>
                </div>
                <div>
                  <span className="font-medium text-slate-500">التاريخ:</span>{" "}
                  <span>{new Date(selectedLog.created_at).toLocaleString("ar-IQ")}</span>
                </div>
                <div>
                  <span className="font-medium text-slate-500">عنوان IP:</span>{" "}
                  <span className="font-mono">{selectedLog.ip_address ?? "—"}</span>
                </div>
                <div>
                  <span className="font-medium text-slate-500">معرف الكيان:</span>{" "}
                  <span className="font-mono">{selectedLog.auditable_id ?? "—"}</span>
                </div>
              </div>

              {/* Old vs New Values */}
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {/* Old Values */}
                <div>
                  <h4 className="mb-1.5 text-xs font-bold text-red-700 flex items-center gap-1">
                    <span className="h-2 w-2 rounded-full bg-red-500"></span>
                    القيم السابقة (Old Values)
                  </h4>
                  <pre className="max-h-60 overflow-auto rounded-lg bg-red-50/70 p-3 font-mono text-xs text-red-900 border border-red-200">
                    {selectedLog.old_values && Object.keys(selectedLog.old_values).length > 0
                      ? JSON.stringify(selectedLog.old_values, null, 2)
                      : "لا توجد قيم سابقة (سجل جديد)"}
                  </pre>
                </div>

                {/* New Values */}
                <div>
                  <h4 className="mb-1.5 text-xs font-bold text-emerald-700 flex items-center gap-1">
                    <span className="h-2 w-2 rounded-full bg-emerald-500"></span>
                    القيم الجديدة (New Values)
                  </h4>
                  <pre className="max-h-60 overflow-auto rounded-lg bg-emerald-50/70 p-3 font-mono text-xs text-emerald-900 border border-emerald-200">
                    {selectedLog.new_values && Object.keys(selectedLog.new_values).length > 0
                      ? JSON.stringify(selectedLog.new_values, null, 2)
                      : "لا توجد قيم مسجلة"}
                  </pre>
                </div>
              </div>

              <div className="mt-2 flex justify-end">
                <Button variant="outline" onClick={() => setSelectedLog(null)}>
                  إغلاق
                </Button>
              </div>
            </div>
          </Modal>
        )}
      </div>
    </PermissionGate>
  );
}

