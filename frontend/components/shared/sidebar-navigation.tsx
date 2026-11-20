// Navigation landmark of the signed in layout, showing only the items allowed for the user's role.
// Marks the item matching the current path as the current page.

"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { NavigationMessage } from "@/constants/navigation";
import { useAuth } from "@/hooks/auth/use-auth";
import { findActiveNavigationItem, navigationItemsForRole } from "@/lib/routing/navigation";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

interface SidebarNavigationProps {
  onNavigate?: () => void;
  className?: string;
}

export function SidebarNavigation({ onNavigate, className }: SidebarNavigationProps) {
  const { user } = useAuth();
  const pathname = usePathname();

  if (user === null) {
    return null;
  }

  const items = navigationItemsForRole(user.role);
  const activeItem = findActiveNavigationItem(items, pathname);

  return (
    <nav aria-label={NavigationMessage.NavigationLabel} className={className}>
      <ul className="flex flex-col gap-1">
        {items.map((item) => {
          const isActive = item.route === activeItem?.route;

          return (
            <li key={item.route}>
              <Link
                href={item.route}
                aria-current={isActive ? "page" : undefined}
                onNavigate={onNavigate}
                className={mergeClassNames(
                  "flex items-center rounded-lg px-3 py-2.5 text-base transition-colors",
                  "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary",
                  isActive
                    ? "bg-primary/15 font-semibold text-foreground"
                    : "text-foreground hover:bg-surface-muted",
                )}
              >
                {item.label}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
