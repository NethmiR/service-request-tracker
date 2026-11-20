// Shared classes for text inputs, text areas and selects.
// Keeps every form control the same height, border, focus ring and error style.

export const FORM_CONTROL_CLASS_NAMES = [
  "w-full rounded-lg border border-border bg-surface px-3 text-sm text-foreground transition-colors",
  "placeholder:text-placeholder",
  "focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-primary/30",
  "disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-muted",
  "aria-invalid:border-danger aria-invalid:focus-visible:outline-danger/30",
].join(" ");
