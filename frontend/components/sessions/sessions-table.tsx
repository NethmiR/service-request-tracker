// Presentational table of active sessions with the device, IP address, last activity and an end action.
// Holds no data loading, so the account page and the admin user sessions view can both use it.

import { Badge } from "@/components/shared/badge";
import { Button } from "@/components/shared/button";
import { Table, type TableColumn } from "@/components/shared/table";
import { SessionMessage } from "@/constants/session-messages";
import { BadgeTone } from "@/enums/ui/badge-tone";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { SessionTableColumn } from "@/enums/ui/session-table-column";
import { describeUserAgent } from "@/lib/sessions/describe-user-agent";
import { formatDateTime } from "@/lib/utilities/format-date-time";
import type { UserSession } from "@/types/sessions/user-session";

interface SessionsTableProps {
  sessions: UserSession[];
  onClearSession: (session: UserSession) => void;
  clearingSessionId: string | null;
}

export function SessionsTable({ sessions, onClearSession, clearingSessionId }: SessionsTableProps) {
  const columns: TableColumn<UserSession>[] = [
    {
      key: SessionTableColumn.Device,
      header: SessionMessage.DeviceColumn,
      cell: (session) => (
        <div className="flex flex-wrap items-center gap-2">
          <span
            title={session.user_agent ?? undefined}
            className="font-medium whitespace-nowrap text-foreground"
          >
            {describeUserAgent(session.user_agent)}
          </span>
          {session.is_current && <Badge tone={BadgeTone.Success}>{SessionMessage.CurrentDevice}</Badge>}
        </div>
      ),
    },
    {
      key: SessionTableColumn.IpAddress,
      header: SessionMessage.IpAddressColumn,
      cell: (session) => (
        <span className="whitespace-nowrap tabular-nums">
          {session.ip_address ?? SessionMessage.UnknownIpAddress}
        </span>
      ),
    },
    {
      key: SessionTableColumn.LastActive,
      header: SessionMessage.LastActiveColumn,
      cell: (session) => (
        <time dateTime={session.last_active_at} className="whitespace-nowrap">
          {formatDateTime(session.last_active_at)}
        </time>
      ),
    },
    {
      key: SessionTableColumn.Actions,
      header: <span className="sr-only">{SessionMessage.ActionsColumn}</span>,
      className: "text-right",
      cell: (session) =>
        session.is_current ? null : (
          <Button
            variant={ButtonVariant.Danger}
            size={ComponentSize.Small}
            isLoading={clearingSessionId === session.id}
            disabled={clearingSessionId !== null}
            onClick={() => onClearSession(session)}
          >
            {SessionMessage.EndSession}
          </Button>
        ),
    },
  ];

  return (
    <Table
      columns={columns}
      rows={sessions}
      rowKey={(session) => session.id}
      caption={SessionMessage.TableCaption}
    />
  );
}
