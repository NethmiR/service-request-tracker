// Button that signs the current user out and shows a loading state while the request runs.
// The route guards perform the navigation once the auth state is cleared.

"use client";

import { useState } from "react";
import { Button } from "@/components/shared/button";
import { AuthMessage } from "@/constants/auth-messages";
import { ButtonVariant } from "@/enums/ui/button-variant";
import { ComponentSize } from "@/enums/ui/component-size";
import { useAuth } from "@/hooks/auth/use-auth";

interface SignOutButtonProps {
  variant?: ButtonVariant;
  size?: ComponentSize;
  className?: string;
}

export function SignOutButton({
  variant = ButtonVariant.Secondary,
  size = ComponentSize.Medium,
  className,
}: SignOutButtonProps) {
  const { signOut } = useAuth();
  const [isSigningOut, setIsSigningOut] = useState(false);

  async function handleClick() {
    setIsSigningOut(true);
    await signOut();
    setIsSigningOut(false);
  }

  return (
    <Button
      variant={variant}
      size={size}
      isLoading={isSigningOut}
      onClick={handleClick}
      className={className}
    >
      {AuthMessage.SignOutButton}
    </Button>
  );
}
