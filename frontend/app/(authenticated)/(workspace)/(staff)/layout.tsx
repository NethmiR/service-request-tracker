// Layout of every page for technicians and admins only, guarded by RequireRole with the staff roles.
// Holds the overview and the request dashboard, and other roles are sent to their home page.

import type { ReactNode } from "react";
import { RequireRole } from "@/components/auth/require-role";
import { STAFF_ROLES } from "@/constants/role-groups";

interface StaffLayoutProps {
  children: ReactNode;
}

export default function StaffLayout({ children }: StaffLayoutProps) {
  return <RequireRole allowedRoles={STAFF_ROLES}>{children}</RequireRole>;
}
