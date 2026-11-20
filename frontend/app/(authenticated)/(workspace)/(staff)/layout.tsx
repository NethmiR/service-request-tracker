// Layout of every page for technicians and admins only, guarded by RequireRole with the staff roles.
// Other roles are sent to their home page. Phase 6 adds the requests dashboard to this group.

import type { ReactNode } from "react";
import { RequireRole } from "@/components/auth/require-role";
import { STAFF_ROLES } from "@/constants/role-groups";

interface StaffLayoutProps {
  children: ReactNode;
}

export default function StaffLayout({ children }: StaffLayoutProps) {
  return <RequireRole allowedRoles={STAFF_ROLES}>{children}</RequireRole>;
}
