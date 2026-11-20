// Signed in layout with a fixed sidebar from the large breakpoint and a top bar with a menu below it.
// The sidebar holds the brand, the role based navigation and the account summary next to the content panel.

"use client";

import { useId, useState } from "react";
import type { ReactNode } from "react";
import { Brand } from "@/components/shared/brand";
import { Button } from "@/components/shared/button";
import { SidebarAccountSummary } from "@/components/shared/sidebar-account-summary";
import { SidebarNavigation } from "@/components/shared/sidebar-navigation";
import { NavigationMessage } from "@/constants/navigation";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

const MENU_BAR_CLASS_NAMES = "block h-0.5 w-5 rounded-full bg-current transition-transform";

interface WorkspaceShellProps {
  children: ReactNode;
}

export function WorkspaceShell({ children }: WorkspaceShellProps) {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const menuPanelId = useId();

  function toggleMenu() {
    setIsMenuOpen((isOpen) => !isOpen);
  }

  function closeMenu() {
    setIsMenuOpen(false);
  }

  return (
    <div className="flex flex-1 flex-col lg:flex-row">
      <aside className="hidden bg-surface lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-72 lg:shrink-0 lg:flex-col lg:gap-8 lg:overflow-y-auto lg:px-4 lg:py-6">
        <Brand className="px-2" />
        <SidebarNavigation className="flex-1" />
        <SidebarAccountSummary />
      </aside>

      <header className="bg-surface lg:hidden">
        <div className="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
          <Brand />
          <Button
            variant={ButtonVariant.Ghost}
            aria-expanded={isMenuOpen}
            aria-controls={menuPanelId}
            aria-label={isMenuOpen ? NavigationMessage.CloseMenu : NavigationMessage.OpenMenu}
            onClick={toggleMenu}
            className="size-10 flex-col gap-1.5 px-0"
          >
            <span
              aria-hidden="true"
              className={mergeClassNames(MENU_BAR_CLASS_NAMES, isMenuOpen && "translate-y-2 rotate-45")}
            />
            <span
              aria-hidden="true"
              className={mergeClassNames(MENU_BAR_CLASS_NAMES, isMenuOpen && "opacity-0")}
            />
            <span
              aria-hidden="true"
              className={mergeClassNames(MENU_BAR_CLASS_NAMES, isMenuOpen && "-translate-y-2 -rotate-45")}
            />
          </Button>
        </div>
        <div
          id={menuPanelId}
          hidden={!isMenuOpen}
          className="flex flex-col gap-4 border-t border-border-subtle px-4 pt-3 pb-4 sm:px-6"
        >
          <SidebarNavigation onNavigate={closeMenu} />
          <SidebarAccountSummary />
        </div>
      </header>

      <div className="flex min-w-0 flex-1 flex-col p-4 sm:p-6">
        <main className="flex flex-1 flex-col gap-6 rounded-lg bg-surface p-6 sm:p-8">
          {children}
        </main>
      </div>
    </div>
  );
}
