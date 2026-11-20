// Root route that sends signed in users to their home page and everyone else to sign in.
// There is no landing page, so this route only shows the loader while it redirects.

import { RootRedirect } from "@/components/auth/root-redirect";

export default function RootPage() {
  return <RootRedirect />;
}
