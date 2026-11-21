"use client";

import { useId, useState } from "react";
import { ChevronDownIcon } from "@/components/shared/chevron-down-icon";
import {
  SIDEBAR_NAVIGATION_ENTRY_CLASS_NAMES,
  SIDEBAR_NAVIGATION_INACTIVE_CLASS_NAMES,
} from "@/components/shared/sidebar-navigation-class-names";
import { SidebarNavigationLink } from "@/components/shared/sidebar-navigation-link";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";
import type { NavigationGroup, NavigationLink } from "@/types/shared/navigation-item";

interface SidebarNavigationGroupProps {
  group: NavigationGroup;
  activeLink: NavigationLink | null;
  onNavigate?: () => void;
}

export function SidebarNavigationGroup({
  group,
  activeLink,
  onNavigate,
}: SidebarNavigationGroupProps) {
  const linksId = useId();
  const containsActiveLink = group.links.some((link) => link.route === activeLink?.route);
  const [isExpanded, setIsExpanded] = useState(containsActiveLink);
  const [previousContainsActiveLink, setPreviousContainsActiveLink] = useState(containsActiveLink);

  if (containsActiveLink !== previousContainsActiveLink) {
    setPreviousContainsActiveLink(containsActiveLink);

    if (containsActiveLink) {
      setIsExpanded(true);
    }
  }

  function toggleExpanded() {
    setIsExpanded((wasExpanded) => !wasExpanded);
  }

  return (
    <>
      <button
        type="button"
        aria-expanded={isExpanded}
        aria-controls={linksId}
        onClick={toggleExpanded}
        className={mergeClassNames(
          SIDEBAR_NAVIGATION_ENTRY_CLASS_NAMES,
          SIDEBAR_NAVIGATION_INACTIVE_CLASS_NAMES,
          "cursor-pointer justify-between text-left",
          containsActiveLink && "font-semibold",
        )}
      >
        {group.label}
        <ChevronDownIcon
          className={mergeClassNames(
            "size-4 shrink-0 text-muted transition-transform",
            isExpanded && "rotate-180",
          )}
        />
      </button>
      <ul id={linksId} hidden={!isExpanded} className="mt-1 flex flex-col gap-1">
        {group.links.map((link) => (
          <li key={link.route}>
            <SidebarNavigationLink
              link={link}
              isActive={link.route === activeLink?.route}
              onNavigate={onNavigate}
              className="pl-7"
            />
          </li>
        ))}
      </ul>
    </>
  );
}
