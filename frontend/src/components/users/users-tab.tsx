"use client";

import { useMemo, useState } from "react";
import { Plus, Search, X } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { usePaginatedResource, useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { formatDateTime } from "@/lib/format";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { Input, Select, Checkbox } from "@/components/ui/field";
import { Badge } from "@/components/ui/badge";
import { Modal } from "@/components/ui/modal";
import { Drawer, DetailRow, DetailSection } from "@/components/ui/drawer";
import { useToast } from "@/components/ui/toast";
import type { Branch, Role, User, Warehouse } from "@/lib/types";

export function UsersTab() {
  const { hasPermission, user: me } = useAuth();
  const isSuperAdmin =
    me?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.usersManage);
  const toast = useToast();

  const [search, setSearch] = useState("");
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<User | null>(null);
  const [selected, setSelected] = useState<User | null>(null);
  const [busy, setBusy] = useState(false);

  const list = usePaginatedResource<User>("/v1/users", {
    search: search || undefined,
  });
  const branches = useSimpleList<Branch>("/v1/branches");
  const roles = useSimpleList<Role>("/v1/roles");
  const warehouses = useSimpleList<Warehouse>("/v1/warehouses");

  async function toggleActive(u: User) {
    setBusy(true);
    try {
      if (u.is_active) {
        await api.del(`/v1/users/${u.id}`);
        toast.success("تم تعطيل حساب المستخدم.");
      } else {
        await api.put(`/v1/users/${u.id}`, { is_active: true });
        toast.success("تم تفعيل حساب المستخدم.");
      }
      list.refetch();
      setSelected(null);
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "تعذر تحديث حالة هذا المستخدم.",
      );
    } finally {
      setBusy(false);
    }
  }

  const columns: Column<User>[] = [
    {
      key: "name",
      header: "الاسم الكامل",
      render: (u) => (
        <span className="font-medium text-slate-900">{u.full_name}</span>
      ),
    },
    { key: "username", header: "اسم المستخدم", render: (u) => u.username },
    { key: "email", header: "البريد الإلكتروني", render: (u) => u.email ?? "—" },
    { key: "branch", header: "المكتب / الفرع", render: (u) => u.branch?.name ?? "—" },
    {
      key: "roles",
      header: "الأدوار والصلاحيات",
      render: (u) => (
        <div className="flex flex-wrap gap-1">
          {(u.roles ?? []).length === 0 && (
            <span className="text-slate-400">—</span>
          )}
          {(u.roles ?? []).map((r) => (
            <Badge key={r.user_role_id} tone="blue">
              {r.name}
            </Badge>
          ))}
        </div>
      ),
    },
    {
      key: "status",
      header: "الحالة",
      render: (u) => (
        <Badge tone={u.is_active ? "emerald" : "slate"}>
          {u.is_active ? "نشط" : "معطل"}
        </Badge>
      ),
    },
  ];

  return (
    <Card>
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
        <div className="relative w-full max-w-xs">
          <Search className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="بحث بالاسم أو اسم المستخدم..."
            className="h-9 w-full rounded-lg border border-slate-300 bg-white pr-8 pl-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
          />
        </div>
        {canManage && (
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" /> إضافة موظف جديد
          </Button>
        )}
      </div>
      <DataTable
        columns={columns}
        rows={list.data}
        rowKey={(u) => u.id}
        loading={list.loading}
        error={list.error}
        onRowClick={(u) => setSelected(u)}
        emptyTitle="لم يتم العثور على أي موظف"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {(creating || editing) && (
        <UserFormModal
          user={editing}
          branches={branches.data}
          onClose={() => {
            setCreating(false);
            setEditing(null);
          }}
          onDone={() => {
            setCreating(false);
            setEditing(null);
            list.refetch();
          }}
        />
      )}

      {selected && (
        <Drawer
          open
          onClose={() => setSelected(null)}
          title={selected.full_name}
          subtitle={`@${selected.username}`}
        >
          <DetailSection title="البيانات الأساسية للموظف">
            <DetailRow label="البريد الإلكتروني" value={selected.email ?? "—"} />
            <DetailRow label="رقم الهاتف" value={selected.phone ?? "—"} />
            <DetailRow label="المكتب / الفرع" value={selected.branch?.name ?? "الإدارة الرئيسية"} />
            <DetailRow
              label="حالة الحساب"
              value={
                <Badge tone={selected.is_active ? "emerald" : "slate"}>
                  {selected.is_active ? "نشط" : "معطل"}
                </Badge>
              }
            />
            <DetailRow
              label="آخر تسجيل دخول"
              value={
                selected.last_login_at
                  ? formatDateTime(selected.last_login_at)
                  : "لم يسجل دخول بعد"
              }
            />
          </DetailSection>

          <DetailSection title="الأدوار والصلاحيات الممنوحة">
            <div className="space-y-2">
              {(selected.roles ?? []).length === 0 && (
                <p className="py-1 text-xs text-slate-400">
                  لا توجد أدوار معينة لهذا الموظف بعد.
                </p>
              )}
              {(selected.roles ?? []).map((r) => (
                <div
                  key={r.user_role_id}
                  className="flex items-center justify-between gap-3 py-1"
                >
                  <div>
                    <p className="text-sm font-medium text-slate-900">
                      {r.name}
                    </p>
                    <p className="text-xs text-slate-400">
                      {r.scope.branch_id
                        ? (branches.data.find((b) => b.id === r.scope.branch_id)
                            ?.name ?? "فرع محدد")
                        : "كافة الفروع"}
                      {r.scope.warehouse_id
                        ? ` · ${warehouses.data.find((w) => w.id === r.scope.warehouse_id)?.name ?? "مخزن"}`
                        : ""}
                    </p>
                  </div>
                  {canManage && (
                    <button
                      onClick={async () => {
                        setBusy(true);
                        try {
                          await api.del(
                            `/v1/users/${selected.id}/roles/${r.user_role_id}`,
                          );
                          toast.success("تم سحب الدور بنجاح.");
                          list.refetch();
                          setSelected(null);
                        } catch (err) {
                          toast.error(
                            err instanceof ApiError
                              ? err.summary
                              : "تعذر سحب هذا الدور.",
                          );
                        } finally {
                          setBusy(false);
                        }
                      }}
                      disabled={busy}
                      className="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-500"
                      aria-label="سحب الدور"
                    >
                      <X className="h-3.5 w-3.5" />
                    </button>
                  )}
                </div>
              ))}
            </div>
          </DetailSection>

          {canManage && (
            <AssignRoleForm
              userId={selected.id}
              roles={roles.data}
              branches={branches.data}
              warehouses={warehouses.data}
              onDone={() => {
                list.refetch();
                setSelected(null);
              }}
            />
          )}

          {canManage && (
            <div className="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
              <Button
                size="sm"
                variant="outline"
                onClick={() => setEditing(selected)}
              >
                تعديل البيانات
              </Button>
              <Button
                size="sm"
                variant="outline"
                loading={busy}
                onClick={() => toggleActive(selected)}
              >
                {selected.is_active ? "تعطيل الحساب" : "تفعيل الحساب"}
              </Button>
            </div>
          )}
        </Drawer>
      )}
    </Card>
  );
}

function AssignRoleForm({
  userId,
  roles,
  branches,
  warehouses,
  onDone,
}: {
  userId: string;
  roles: Role[];
  branches: Branch[];
  warehouses: Warehouse[];
  onDone: () => void;
}) {
  const toast = useToast();
  const [roleId, setRoleId] = useState("");
  const [branchId, setBranchId] = useState("");
  const [warehouseId, setWarehouseId] = useState("");
  const [busy, setBusy] = useState(false);

  const warehouseOptions = useMemo(
    () => warehouses.filter((w) => !branchId || w.branch_id === branchId),
    [warehouses, branchId],
  );

  async function assign() {
    if (!roleId) return;
    setBusy(true);
    try {
      await api.post(`/v1/users/${userId}/roles`, {
        role_id: roleId,
        branch_id: branchId || undefined,
        warehouse_id: warehouseId || undefined,
      });
      toast.success("تم إسناد الدور بنجاح.");
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "تعذر إسناد هذا الدور.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <DetailSection title="إسناد دور وصلاحية جديدة">
      <div className="grid grid-cols-1 gap-3 py-1 sm:grid-cols-3">
        <Select
          value={roleId}
          onChange={(e) => setRoleId(e.target.value)}
          placeholder="اختر الدور..."
          wrapClassName="sm:col-span-1"
        >
          {roles.map((r) => (
            <option key={r.id} value={r.id}>
              {r.name}
            </option>
          ))}
        </Select>
        <Select
          value={branchId}
          onChange={(e) => {
            setBranchId(e.target.value);
            setWarehouseId("");
          }}
          placeholder="كافة الفروع (اختياري)"
        >
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {b.name}
            </option>
          ))}
        </Select>
        <Select
          value={warehouseId}
          onChange={(e) => setWarehouseId(e.target.value)}
          placeholder="كافة المخازن (اختياري)"
        >
          {warehouseOptions.map((w) => (
            <option key={w.id} value={w.id}>
              {w.name}
            </option>
          ))}
        </Select>
      </div>
      <Button
        size="sm"
        className="mt-1"
        disabled={!roleId}
        loading={busy}
        onClick={assign}
        type="button"
      >
        إسناد الدور للموظف
      </Button>
    </DetailSection>
  );
}

function UserFormModal({
  user,
  branches,
  onClose,
  onDone,
}: {
  user: User | null;
  branches: Branch[];
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const [branchId, setBranchId] = useState(user?.branch?.id ?? "");
  const [username, setUsername] = useState(user?.username ?? "");
  const [email, setEmail] = useState(user?.email ?? "");
  const [fullName, setFullName] = useState(user?.full_name ?? "");
  const [phone, setPhone] = useState(user?.phone ?? "");
  const [isActive, setIsActive] = useState(user?.is_active ?? true);
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    setBusy(true);
    try {
      const payload: Record<string, unknown> = {
        branch_id: branchId || undefined,
        username,
        email: email || undefined,
        full_name: fullName,
        phone: phone || undefined,
        is_active: isActive,
      };
      if (password) {
        payload.password = password;
        payload.password_confirmation = passwordConfirmation;
      }
      if (user) {
        await api.put(`/v1/users/${user.id}`, payload);
        toast.success("تم تحديث بيانات الموظف بنجاح.");
      } else {
        await api.post("/v1/users", payload);
        toast.success("تم إنشاء حساب الموظف بنجاح.");
      }
      onDone();
    } catch (err) {
      if (err instanceof ApiError && err.errors) {
        setErrors(err.errors);
        toast.error(err.summary);
      } else {
        toast.error(
          err instanceof ApiError ? err.summary : "تعذر حفظ بيانات المستخدم.",
        );
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={user ? `تعديل بيانات: ${user.full_name}` : "إضافة موظف جديد"}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            إلغاء
          </Button>
          <Button onClick={submit} loading={busy}>
            {user ? "حفظ التعديلات" : "إنشاء الحساب"}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Input
          label="الاسم الكامل"
          required
          value={fullName}
          onChange={(e) => setFullName(e.target.value)}
          error={errors.full_name?.[0]}
          wrapClassName="col-span-2"
        />
        <Input
          label="اسم المستخدم"
          required
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          error={errors.username?.[0]}
        />
        <Input
          label="البريد الإلكتروني"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          error={errors.email?.[0]}
        />
        <Input
          label="رقم الهاتف"
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
        />
        <Select
          label="المكتب / الفرع"
          value={branchId}
          onChange={(e) => setBranchId(e.target.value)}
          placeholder="بدون فرع (الإدارة المركزية)"
        >
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {b.name}
            </option>
          ))}
        </Select>
        <Input
          label={
            user ? "كلمة مرور جديدة (اتركه فارغاً للإبقاء على الحالية)" : "كلمة المرور"
          }
          type="password"
          required={!user}
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          error={errors.password?.[0]}
          hint="8 أحرف على الأقل، تتضمن حروفاً وأرقاماً."
        />
        <Input
          label="تأكيد كلمة المرور"
          type="password"
          required={!user ? true : Boolean(password)}
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
        />
        <div className="col-span-2 pt-1">
          <Checkbox
            label="حساب نشط ومفعل"
            checked={isActive}
            onChange={(e) => setIsActive(e.target.checked)}
          />
        </div>
      </form>
    </Modal>
  );
}
