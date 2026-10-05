"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Pill, ShieldCheck, Calculator, Building2, PackageCheck } from "lucide-react";
import { useAuth } from "@/lib/auth-context";
import { ApiError } from "@/lib/api";
import { Input } from "@/components/ui/field";
import { Button } from "@/components/ui/button";

const DEMO_USERS = [
  {
    role: "المدير العام (Super Admin)",
    username: "admin",
    icon: ShieldCheck,
    desc: "تحكم كامل • جميع الفروع والمخازن والمحاسبة",
  },
  {
    role: "المحاسب (Accountant)",
    username: "accountant",
    icon: Calculator,
    desc: "شجرة الحسابات • القيود • الأرباح والميزانية",
  },
  {
    role: "مدير مكتب A (Branch Manager)",
    username: "manager_a",
    icon: Building2,
    desc: "مكتب ومخزن A فقط • مبيعات ومشتريات ومخزون",
  },
  {
    role: "موظف مخزن A (Warehouse)",
    username: "warehouse_a",
    icon: PackageCheck,
    desc: "استلام وصرف • جرد وتواريخ الصلاحية",
  },
];

export default function LoginPage() {
  const { user, loading, login } = useAuth();
  const router = useRouter();
  const [username, setUsername] = useState("admin");
  const [password, setPassword] = useState("Passw0rd!");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!loading && user) {
      router.replace("/dashboard");
    }
  }, [loading, user, router]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await login(username, password);
      router.replace("/dashboard");
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.summary
          : "بيانات الدخول غير صحيحة، يرجى المحاولة مرة أخرى.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  function pickUser(u: string) {
    setUsername(u);
    setPassword("Passw0rd!");
    setError(null);
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-100/70 px-4 py-8">
      <div className="w-full max-w-md">
        <div className="mb-6 flex flex-col items-center gap-2 text-center">
          <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-700 text-white shadow-md shadow-emerald-700/20">
            <Pill className="h-7 w-7" />
          </div>
          <div>
            <h1 className="text-xl font-bold tracking-tight text-slate-900">
              شركة سهل الحضارات للأدوية البيطرية
            </h1>
            <p className="text-xs font-medium text-emerald-800 mt-0.5">
              نظام الإدارة والمحاسبة والمخازن المتكامل (ERP)
            </p>
          </div>
        </div>

        <form
          onSubmit={onSubmit}
          className="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm"
        >
          {error && (
            <div className="rounded-lg bg-red-50 p-3 text-xs font-medium text-red-700">
              {error}
            </div>
          )}
          <Input
            label="اسم المستخدم / Username"
            required
            autoFocus
            autoComplete="username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            placeholder="admin"
          />
          <Input
            label="كلمة المرور / Password"
            type="password"
            required
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
          />
          <Button
            type="submit"
            size="lg"
            loading={submitting}
            className="mt-2 w-full bg-emerald-700 hover:bg-emerald-800 text-white font-medium"
          >
            تسجيل الدخول / Sign In
          </Button>
        </form>

        <div className="mt-5 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
          <p className="mb-2 text-xs font-semibold text-slate-700">
            تجربة الأدوار والصلاحيات (RBAC Quick-Switch):
          </p>
          <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
            {DEMO_USERS.map((item) => {
              const Icon = item.icon;
              const isSelected = username === item.username;
              return (
                <button
                  key={item.username}
                  type="button"
                  onClick={() => pickUser(item.username)}
                  className={`flex flex-col items-start rounded-lg border p-2 text-left transition-all ${
                    isSelected
                      ? "border-emerald-600 bg-emerald-50/60"
                      : "border-slate-100 bg-slate-50/50 hover:bg-slate-100/70"
                  }`}
                >
                  <div className="flex items-center gap-1.5">
                    <Icon className="h-3.5 w-3.5 text-emerald-700" />
                    <span className="text-xs font-semibold text-slate-800">
                      {item.role}
                    </span>
                  </div>
                  <span className="text-[10px] text-slate-500 mt-0.5 line-clamp-1">
                    {item.desc}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
