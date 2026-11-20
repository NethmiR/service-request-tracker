// Presentational table of users with their name, email, role, password status and creation date.
// Each name links to the user detail page, and the table holds no data loading.

import Link from "next/link";
import { Badge } from "@/components/shared/badge";
import { Table, type TableColumn } from "@/components/shared/table";
import { USER_ROLE_LABELS } from "@/constants/enum-labels";
import { userDetailRoute } from "@/constants/routes";
import { UserMessage } from "@/constants/user-messages";
import { BadgeTone } from "@/enums/ui/badge-tone";
import { UserTableColumn } from "@/enums/ui/user-table-column";
import { passwordStatusLabel, passwordStatusTone } from "@/lib/users/password-status";
import { formatDateTime } from "@/lib/utilities/format-date-time";
import type { ManagedUser } from "@/types/users/managed-user";

const USER_COLUMNS: TableColumn<ManagedUser>[] = [
  {
    key: UserTableColumn.Name,
    header: UserMessage.NameColumn,
    cell: (user) => (
      <Link
        href={userDetailRoute(user.id)}
        className="font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-primary"
      >
        {user.name}
      </Link>
    ),
  },
  {
    key: UserTableColumn.Email,
    header: UserMessage.EmailColumn,
    cell: (user) => <span className="break-all text-foreground">{user.email}</span>,
  },
  {
    key: UserTableColumn.Role,
    header: UserMessage.RoleColumn,
    cell: (user) => <Badge tone={BadgeTone.Info}>{USER_ROLE_LABELS[user.role]}</Badge>,
  },
  {
    key: UserTableColumn.PasswordStatus,
    header: UserMessage.PasswordColumn,
    cell: (user) => <Badge tone={passwordStatusTone(user)}>{passwordStatusLabel(user)}</Badge>,
  },
  {
    key: UserTableColumn.CreatedAt,
    header: UserMessage.CreatedColumn,
    cell: (user) => (
      <time dateTime={user.created_at} className="whitespace-nowrap">
        {formatDateTime(user.created_at)}
      </time>
    ),
  },
];

interface UsersTableProps {
  users: ManagedUser[];
}

export function UsersTable({ users }: UsersTableProps) {
  return (
    <Table
      columns={USER_COLUMNS}
      rows={users}
      rowKey={(user) => user.id}
      caption={UserMessage.TableCaption}
      emptyTitle={UserMessage.EmptyTitle}
      emptyDescription={UserMessage.EmptyDescription}
    />
  );
}
