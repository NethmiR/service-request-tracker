// Layout of every admin only page, guarded by RequireRole with the admin roles.
// Every admin only page lives in this group, and other roles are sent to their home page.

import type { ReactNode } from "react";
import { RequireRole } from "@/components/auth/require-role";
import { ADMIN_ROLES } from "@/constants/role-groups";

interface AdminLayoutProps {
  children: ReactNode;
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  return <RequireRole allowedRoles={ADMIN_ROLES}>{children}</RequireRole>;
}
