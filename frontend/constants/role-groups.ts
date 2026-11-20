// Named groups of roles shared by the navigation items and the page level role guards.
// Features refer to these groups instead of listing the same roles again.

import { UserRole } from "@/enums/user-role";

export const ALL_ROLES: readonly UserRole[] = Object.values(UserRole);

export const STAFF_ROLES: readonly UserRole[] = [UserRole.Admin, UserRole.Technician];

export const ADMIN_ROLES: readonly UserRole[] = [UserRole.Admin];
