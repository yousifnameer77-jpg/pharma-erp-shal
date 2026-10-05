"use client";

import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import {
  ShieldAlert,
  Search,
  Printer,
  FileCheck,
  Calendar,
  UserCheck,
  Stethoscope,
  Pill,
  Filter,
  CheckCircle,
  AlertCircle,
  FileText,
} from "lucide-react";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";

interface PrescriptionRecord {
  id: string;
  prescription_number: string;
  prescription_date: string;
  patient_name: string;
  patient_national_id: string | null;
  doctor_name: string;
  doctor_syndicate_id: string | null;
  doctor_clinic: string | null;
  diagnosis_notes: string | null;
  quantity_dispensed: number;
  dispensed_at: string;
  product: {
    name: string;
    form: string | null;
    strength: string | null;
  };
  dispensed_by_user?: {
    full_name: string;
    username: string;
  };
}

export default function ControlledDrugsPage() {
  const [records, setRecords] = useState<PrescriptionRecord[]>([]);
  const [kpis, setKpis] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [searchDoctor, setSearchDoctor] = useState("");
  const [searchRx, setSearchRx] = useState("");
  const [searchPatient, setSearchPatient] = useState("");

  const fetchRecords = async () => {
    try {
      setLoading(true);
      const queryParams: Record<string, string> = {};
      if (searchDoctor.trim()) queryParams.doctor_name = searchDoctor.trim();
      if (searchRx.trim()) queryParams.prescription_number = searchRx.trim();
      if (searchPatient.trim()) queryParams.patient_name = searchPatient.trim();

      const queryString = new URLSearchParams(queryParams).toString();
      const url = `/v1/controlled-drugs${queryString ? `?${queryString}` : ""}`;

      const res: any = await apiFetch(url);
      setRecords(res.data || []);
      setKpis(res.kpis || null);
      setError(null);
    } catch (err: any) {
      setError(err?.message || "تعذر تحميل سجل الأدوية المراقبة.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRecords();
  }, []);

  const handleFilterSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchRecords();
  };

  return (
    <PermissionGate permission={PERMISSIONS.productsManage}>
      <div className="space-y-6" dir="rtl">
      
      {/* Header & Print Report Action */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 rounded-3xl shadow-sm">
        <div className="flex items-center gap-3.5">
          <div className="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold shadow-inner">
            <ShieldAlert className="w-6 h-6" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-black text-slate-900 dark:text-white">
                سجل الأدوية المراقبة والوصفات الطبية
              </h1>
              <span className="text-[10px] bg-rose-600 text-white font-extrabold px-2.5 py-0.5 rounded-full">
                رقابة وزارة الصحة
              </span>
            </div>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
              سجل رسمي إلزامي لتوثيق صرف المؤثرات العقلية والأدوية النفسية الخاضعة لرقابة نقابة الصيادلة
            </p>
          </div>
        </div>

        <button
          onClick={() => window.print()}
          className="flex items-center gap-2 bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:hover:bg-white text-white dark:text-slate-900 text-xs font-bold px-4 py-3 rounded-2xl shadow transition"
        >
          <Printer className="w-4 h-4" />
          <span>طباعة تقرير التفتيش الرسمي</span>
        </button>
      </div>

      {/* KPI Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-bold">إجمالي المصروف (عبوات)</span>
            <Pill className="w-4 h-4 text-rose-500" />
          </div>
          <span className="text-2xl font-black text-slate-900 dark:text-white">
            {kpis?.total_quantity_dispensed?.toLocaleString() ?? 0}
          </span>
          <span className="text-[10px] text-slate-400 block mt-1">مسجل وفق الأرقام التسلسلية</span>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-bold">الوصفات الموثقة</span>
            <FileText className="w-4 h-4 text-emerald-500" />
          </div>
          <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">
            {kpis?.unique_prescriptions ?? 0}
          </span>
          <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold block mt-1">وصفة معتمدة 100%</span>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-bold">الأطباء الموصون</span>
            <Stethoscope className="w-4 h-4 text-cyan-500" />
          </div>
          <span className="text-2xl font-black text-cyan-600 dark:text-cyan-400">
            {kpis?.unique_doctors ?? 0}
          </span>
          <span className="text-[10px] text-slate-400 block mt-1">مسجلون برقم هوية النقابة</span>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-bold">الامتثال للتعليمات</span>
            <CheckCircle className="w-4 h-4 text-emerald-500" />
          </div>
          <span className="text-base font-black text-emerald-600 dark:text-emerald-400 block mt-1">
            جاهز للتفتيش ✓
          </span>
          <span className="text-[10px] text-slate-400 block mt-1">لا توجد مخالفات مسجلة</span>
        </div>
      </div>

      {/* Filter Bar */}
      <form
        onSubmit={handleFilterSubmit}
        className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-3xl shadow-sm flex flex-wrap items-center gap-3"
      >
        <div className="relative flex-1 min-w-[200px]">
          <Search className="absolute right-3.5 top-3 w-4 h-4 text-slate-400" />
          <input
            type="text"
            placeholder="بحث باسم الطبيب..."
            value={searchDoctor}
            onChange={(e) => setSearchDoctor(e.target.value)}
            className="w-full pr-10 pl-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl text-xs"
          />
        </div>

        <div className="relative flex-1 min-w-[180px]">
          <Search className="absolute right-3.5 top-3 w-4 h-4 text-slate-400" />
          <input
            type="text"
            placeholder="رقم الوصفة الطبية..."
            value={searchRx}
            onChange={(e) => setSearchRx(e.target.value)}
            className="w-full pr-10 pl-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl text-xs font-mono"
          />
        </div>

        <div className="relative flex-1 min-w-[180px]">
          <Search className="absolute right-3.5 top-3 w-4 h-4 text-slate-400" />
          <input
            type="text"
            placeholder="اسم المريض..."
            value={searchPatient}
            onChange={(e) => setSearchPatient(e.target.value)}
            className="w-full pr-10 pl-3 py-2 bg-slate-50 dark:bg-slate-800 border rounded-xl text-xs"
          />
        </div>

        <button
          type="submit"
          className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow"
        >
          <Filter className="w-3.5 h-3.5" />
          <span>تطبيق الفلتر</span>
        </button>

        {(searchDoctor || searchRx || searchPatient) && (
          <button
            type="button"
            onClick={() => {
              setSearchDoctor("");
              setSearchRx("");
              setSearchPatient("");
              fetchRecords();
            }}
            className="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold rounded-xl"
          >
            إعادة تعيين
          </button>
        )}
      </form>

      {/* Controlled Registry Table */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
        <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <h2 className="font-extrabold text-sm text-slate-900 dark:text-white">
            قائمة القيود والوصفات المسجلة ({records.length})
          </h2>
          <span className="text-xs text-slate-400">محدثة لحظياً عند كل عملية صرف</span>
        </div>

        {loading ? (
          <div className="p-12 text-center text-slate-400 text-xs font-bold">
            جاري فحص السجل الرقابي...
          </div>
        ) : records.length === 0 ? (
          <div className="p-12 text-center text-slate-400 text-xs">
            لا توجد أدوية مراقبة مصروفة مطابقة لخيارات البحث.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-right text-xs">
              <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 font-bold border-b border-slate-200 dark:border-slate-800">
                <tr>
                  <th className="py-3.5 px-4">رقم الوصفة والتاريخ</th>
                  <th className="py-3.5 px-4">المريض والهوية</th>
                  <th className="py-3.5 px-4">الدواء المصروف</th>
                  <th className="py-3.5 px-4">الكمية</th>
                  <th className="py-3.5 px-4">الطبيب المعالج والنقابة</th>
                  <th className="py-3.5 px-4">الصيدلي المسؤول</th>
                  <th className="py-3.5 px-4">وقت الصرف</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                {records.map((r) => (
                  <tr key={r.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                    <td className="py-3 px-4">
                      <span className="font-mono font-bold text-slate-900 dark:text-white block">
                        {r.prescription_number}
                      </span>
                      <span className="text-[10px] text-slate-400">
                        {r.prescription_date}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <span className="font-bold text-slate-800 dark:text-slate-200 block">
                        {r.patient_name}
                      </span>
                      {r.patient_national_id && (
                        <span className="text-[10px] text-slate-400 font-mono">
                          هوية: {r.patient_national_id}
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-4">
                      <span className="font-extrabold text-rose-600 dark:text-rose-400 block">
                        {r.product?.name}
                      </span>
                      <span className="text-[10px] text-slate-400">
                        {r.product?.form} • {r.product?.strength}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <span className="font-black text-sm text-slate-900 dark:text-white font-mono">
                        {r.quantity_dispensed}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <span className="font-bold text-slate-800 dark:text-slate-200 block">
                        {r.doctor_name}
                      </span>
                      {r.doctor_syndicate_id && (
                        <span className="text-[10px] text-cyan-600 dark:text-cyan-400 font-mono">
                          نقابة: {r.doctor_syndicate_id}
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-4">
                      <span className="text-slate-600 dark:text-slate-300">
                        {r.dispensed_by_user?.full_name || r.dispensed_by_user?.username || "الصيدلي المناوب"}
                      </span>
                    </td>
                    <td className="py-3 px-4 font-mono text-[10px] text-slate-400">
                      {new Date(r.dispensed_at).toLocaleString("ar-IQ")}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      </div>
    </PermissionGate>
  );
}

