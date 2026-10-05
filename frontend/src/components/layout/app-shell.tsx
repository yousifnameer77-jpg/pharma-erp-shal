"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth-context";
import { getToken } from "@/lib/api";
import { prefetchResource } from "@/lib/hooks";
import { Sidebar, MobileSidebar } from "@/components/layout/sidebar";
import { Topbar } from "@/components/layout/topbar";
import { LoadingBlock } from "@/components/ui/spinner";

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const router = useRouter();
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    // Only redirect to login if loading is done AND user is absent AND there is no token in storage
    if (!loading && !user && !getToken()) {
      router.replace("/login");
    }
  }, [loading, user, router]);

  useEffect(() => {
    if (user) {
      const timer = setTimeout(() => {
        const companyId = user.company_id ?? (user as any)?.branch?.company_id ?? undefined;
        prefetchResource("/v1/products", { per_page: 15 });
        prefetchResource("/v1/warehouses");
        prefetchResource("/v1/branches");
        prefetchResource("/v1/categories");
        prefetchResource("/v1/manufacturers");
        if (companyId) {
          prefetchResource("/v1/customers", { company_id: companyId });
          prefetchResource("/v1/suppliers", { company_id: companyId });
          prefetchResource("/v1/sales-invoices", { company_id: companyId, per_page: 15 });
          prefetchResource("/v1/purchase-orders", { company_id: companyId, per_page: 15 });
        }
      }, 600);
      return () => clearTimeout(timer);
    }
  }, [user]);

  if (loading) {
    return (
      <div className="flex h-screen items-center justify-center bg-slate-50">
        <LoadingBlock label="جاري التحقق من الجلسة..." />
      </div>
    );
  }

  if (!user && !getToken()) {
    return (
      <div className="flex h-screen items-center justify-center bg-slate-50">
        <LoadingBlock label="جاري التحويل لصفحة الدخول..." />
      </div>
    );
  }

  return (
    <div className="flex h-screen overflow-hidden bg-slate-50">
      <Sidebar />
      <MobileSidebar open={mobileOpen} onClose={() => setMobileOpen(false)} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar onMenuClick={() => setMobileOpen(true)} />
        <main className="flex-1 overflow-y-auto p-4 sm:p-6">
          <div className="mx-auto max-w-7xl">{children}</div>
        </main>
      </div>
    </div>
  );
}
