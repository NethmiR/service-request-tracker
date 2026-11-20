// Props the error boundary files receive from Next.js.
// The thrown value is never shown to users, so only the retry function is read.

export interface ErrorBoundaryProps {
  retry: () => void;
}
