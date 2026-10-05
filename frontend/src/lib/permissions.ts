// Mirrors database/seeders/PermissionSeeder.php on the backend exactly —
// every code here must match a seeded `permissions.code` row or a
// permission check will always fail. Kept as a flat const object (not an
// enum) so string literals from the API compare directly.
export const PERMISSIONS = {
  usersView: "users.view",
  usersManage: "users.manage",
  rolesView: "roles.view",
  rolesManage: "roles.manage",
  branchesManage: "branches.manage",
  inventoryView: "inventory.view",
  inventoryTransferCreate: "inventory.transfer.create",
  inventoryTransferApprove: "inventory.transfer.approve",
  inventoryAdjust: "inventory.adjust",
  productsView: "products.view",
  productsManage: "products.manage",
  batchesView: "batches.view",
  batchesManage: "batches.manage",
  salesView: "sales.view",
  salesCreate: "sales.create",
  salesManage: "sales.manage",
  purchasingView: "purchasing.view",
  purchasingManage: "purchasing.manage",
  purchasingApprove: "purchasing.approve",
  financeJournalView: "finance.journal.view",
  financeManage: "finance.manage",
  reportsView: "reports.view",
} as const;

export type PermissionCode = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
