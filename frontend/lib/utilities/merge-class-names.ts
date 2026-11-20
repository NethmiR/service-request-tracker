// Joins conditional class names and resolves conflicting Tailwind classes.
// Every base component merges its own classes with the caller's className through it.

import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function mergeClassNames(...classNames: ClassValue[]): string {
  return twMerge(clsx(classNames));
}
