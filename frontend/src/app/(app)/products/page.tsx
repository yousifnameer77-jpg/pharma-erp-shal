"use client";

import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { ProductsTab } from "@/components/products/products-tab";
import { CategoriesTab } from "@/components/products/categories-tab";
import { ManufacturersTab } from "@/components/products/manufacturers-tab";

const TABS = [
  { key: "products", label: "دليل الأدوية والمنتجات" },
  { key: "categories", label: "التصنيفات والأنواع" },
  { key: "manufacturers", label: "الشركات المصنعة" },
];

export default function ProductsPage() {
  const [active, setActive] = useState("products");

  return (
    <PermissionGate permission={PERMISSIONS.productsView}>
      <div className="flex flex-col gap-4">
        <PageHeader
          title="دليل الأدوية والمنتجات البيطرية"
          description="إدارة الأصناف الدوائية، المواد الفعالة، التصنيفات، والشركات المصنعة العالمية والمحلية."
        />
        <Tabs tabs={TABS} active={active} onChange={setActive} />
        {active === "products" && <ProductsTab />}
        {active === "categories" && <CategoriesTab />}
        {active === "manufacturers" && <ManufacturersTab />}
      </div>
    </PermissionGate>
  );
}
