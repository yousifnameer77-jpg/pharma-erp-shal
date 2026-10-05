import {
  LayoutDashboard,
  Package,
  Warehouse,
  ShoppingCart,
  Receipt,
  Users,
  Truck,
  Landmark,
  BarChart3,
  UserCog,
  ShieldCheck,
  Monitor,
  ClipboardList,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { PERMISSIONS } from "@/lib/permissions";

export interface NavItem {
  href: string;
  label: string;
  icon: LucideIcon;
  /** null = visible to any authenticated user. */
  permission: string | null;
}

export const NAV_ITEMS: NavItem[] = [
  {
    href: "/dashboard",
    label: "لوحة التحكم",
    icon: LayoutDashboard,
    permission: null,
  },
  {
    href: "/pos",
    label: "نقطة البيع (الكاشير)",
    icon: Monitor,
    permission: PERMISSIONS.salesCreate,
  },
  {
    href: "/products",
    label: "دليل الأدوية والمنتجات",
    icon: Package,
    permission: PERMISSIONS.productsView,
  },
  {
    href: "/warehouses",
    label: "المخازن والمستودعات",
    icon: Warehouse,
    permission: PERMISSIONS.branchesManage,
  },
  {
    href: "/purchases",
    label: "المشتريات والتوريد",
    icon: ShoppingCart,
    permission: PERMISSIONS.purchasingView,
  },
  {
    href: "/sales",
    label: "المبيعات والفواتير",
    icon: Receipt,
    permission: PERMISSIONS.salesView,
  },
  {
    href: "/customers",
    label: "العملاء والعيادات",
    icon: Users,
    permission: PERMISSIONS.salesView,
  },
  {
    href: "/suppliers",
    label: "الموردون والشركات",
    icon: Truck,
    permission: PERMISSIONS.purchasingView,
  },
  {
    href: "/accounting",
    label: "الحسابات العامة",
    icon: Landmark,
    permission: PERMISSIONS.financeJournalView,
  },
  {
    href: "/reports",
    label: "التقارير والتحليلات",
    icon: BarChart3,
    permission: PERMISSIONS.reportsView,
  },
  {
    href: "/users",
    label: "إدارة الموظفين",
    icon: UserCog,
    permission: PERMISSIONS.usersView,
  },
  {
    href: "/audit-logs",
    label: "سجل الرقابة والتدقيق",
    icon: ShieldCheck,
    permission: PERMISSIONS.usersView,
  },
  {
    href: "/controlled-drugs",
    label: "الأدوية المراقبة الخاصة",
    icon: ClipboardList,
    permission: PERMISSIONS.productsManage,
  },
];
