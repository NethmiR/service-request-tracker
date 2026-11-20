// Sign in page with the page heading and the sign in form.
// Signed in users never see it, because the public layout redirects them.

import type { Metadata } from "next";
import { SignInForm } from "@/components/auth/sign-in-form";
import { PageHeader } from "@/components/shared/page-header";
import { AuthMessage } from "@/constants/auth-messages";

export const metadata: Metadata = {
  title: AuthMessage.SignInTitle,
};

export default function SignInPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={AuthMessage.SignInTitle} description={AuthMessage.SignInDescription} />
      <SignInForm />
    </div>
  );
}
