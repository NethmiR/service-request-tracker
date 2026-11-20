// Shared button with visual variants, sizes and a loading state.
// The type defaults to button so it never submits a form by accident.

import type { ComponentProps } from "react";
import { Spinner } from "@/components/shared/spinner";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { mergeClassNames } from "@/lib/utilities/merge-class-names";

const variantClassNames: Record<ButtonVariant, string> = {
  [ButtonVariant.Primary]:
    "border-primary bg-primary text-primary-foreground hover:border-primary-strong hover:bg-primary-strong",
  [ButtonVariant.Secondary]:
    "border-primary bg-primary/10 text-primary-strong hover:bg-primary/20",
  [ButtonVariant.Danger]:
    "border-danger bg-danger text-white hover:border-danger-strong hover:bg-danger-strong",
  [ButtonVariant.Ghost]:
    "border-transparent bg-transparent text-foreground hover:bg-surface-muted",
};

const sizeClassNames: Record<ComponentSize, string> = {
  [ComponentSize.Small]: "h-8 gap-1.5 rounded-full px-3 text-xs",
  [ComponentSize.Medium]: "h-10 gap-2 rounded-lg px-4 text-sm",
  [ComponentSize.Large]: "h-11 gap-2 rounded-lg px-5 text-base",
};

interface ButtonProps extends ComponentProps<"button"> {
  variant?: ButtonVariant;
  size?: ComponentSize;
  isLoading?: boolean;
}

export function Button({
  variant = ButtonVariant.Primary,
  size = ComponentSize.Medium,
  isLoading = false,
  type = "button",
  disabled,
  className,
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || isLoading}
      aria-busy={isLoading || undefined}
      className={mergeClassNames(
        "inline-flex shrink-0 cursor-pointer items-center justify-center border font-medium whitespace-nowrap transition-colors",
        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary",
        "disabled:cursor-not-allowed disabled:opacity-60",
        variantClassNames[variant],
        sizeClassNames[size],
        className,
      )}
      {...props}
    >
      {isLoading && <Spinner size={ComponentSize.Small} />}
      {children}
    </button>
  );
}
