"use client";

import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { UsersTab } from "@/components/users/users-tab";
import { RolesTab } from "@/components/users/roles-tab";

const TABS = [
  { key: "users", label: "المستخدمون والموظفون" },
  { key: "roles", label: "الأدوار والصلاحيات (RBAC)" },
];

function UsersContent() {
  const [active, setActive] = useState("users");

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="إدارة المستخدمين والصلاحيات"
        description="حسابات الموظفين، ربط الفروع والمكاتب، وتعيين الصلاحيات الدقيقة وفق مصفوفة الأمان."
      />
      <Tabs tabs={TABS} active={active} onChange={setActive} />
      {active === "users" && <UsersTab />}
      {active === "roles" && (
        <PermissionGate permission={PERMISSIONS.rolesView}>
          <RolesTab />
        </PermissionGate>
      )}
    </div>
  );
}

export default function UsersPage() {
  return (
    <PermissionGate permission={PERMISSIONS.usersView}>
      <UsersContent />
    </PermissionGate>
  );
}
