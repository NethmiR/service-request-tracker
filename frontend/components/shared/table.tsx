// Generic data table driven by column definitions and a row key function.
// Shows the shared empty state when there are no rows to display.

import type { Key, ReactNode } from "react";
import { EmptyState } from "@/components/shared/empty-state";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

export interface TableColumn<Row> {
  key: string;
  header: ReactNode;
  cell: (row: Row) => ReactNode;
  className?: string;
}

interface TableProps<Row> {
  columns: TableColumn<Row>[];
  rows: Row[];
  rowKey: (row: Row) => Key;
  caption?: string;
  emptyTitle?: string;
  emptyDescription?: string;
  emptyAction?: ReactNode;
  className?: string;
}

export function Table<Row>({
  columns,
  rows,
  rowKey,
  caption,
  emptyTitle,
  emptyDescription,
  emptyAction,
  className,
}: TableProps<Row>) {
  return (
    <div
      className={mergeClassNames(
        "overflow-x-auto rounded-lg border border-border bg-surface",
        className,
      )}
    >
      <table className="w-full border-collapse text-left text-sm">
        {caption !== undefined && <caption className="sr-only">{caption}</caption>}
        <thead className="bg-surface-muted">
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className={mergeClassNames(
                  "border-b border-border px-4 py-3 text-xs font-semibold tracking-wide text-muted uppercase",
                  column.className,
                )}
              >
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length}>
                <EmptyState
                  title={emptyTitle}
                  description={emptyDescription}
                  action={emptyAction}
                />
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr
                key={rowKey(row)}
                className="border-b border-border-subtle transition-colors last:border-b-0 hover:bg-surface-muted"
              >
                {columns.map((column) => (
                  <td
                    key={column.key}
                    className={mergeClassNames("px-4 py-3 align-middle", column.className)}
                  >
                    {column.cell(row)}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
