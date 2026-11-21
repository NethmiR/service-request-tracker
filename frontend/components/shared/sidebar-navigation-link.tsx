import Link from "next/link";
import {
  SIDEBAR_NAVIGATION_ACTIVE_CLASS_NAMES,
  SIDEBAR_NAVIGATION_ENTRY_CLASS_NAMES,
  SIDEBAR_NAVIGATION_INACTIVE_CLASS_NAMES,
} from "@/components/shared/sidebar-navigation-class-names";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";
import type { NavigationLink } from "@/types/shared/navigation-item";

interface SidebarNavigationLinkProps {
  link: NavigationLink;
  isActive: boolean;
  onNavigate?: () => void;
  className?: string;
}

export function SidebarNavigationLink({
  link,
  isActive,
  onNavigate,
  className,
}: SidebarNavigationLinkProps) {
  return (
    <Link
      href={link.route}
      aria-current={isActive ? "page" : undefined}
      onNavigate={onNavigate}
      className={mergeClassNames(
        SIDEBAR_NAVIGATION_ENTRY_CLASS_NAMES,
        isActive ? SIDEBAR_NAVIGATION_ACTIVE_CLASS_NAMES : SIDEBAR_NAVIGATION_INACTIVE_CLASS_NAMES,
        className,
      )}
    >
      {link.label}
    </Link>
  );
}
