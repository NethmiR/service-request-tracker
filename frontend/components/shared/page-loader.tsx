// Full area loading screen with a centred spinner.
// Route guards show it while the auth check runs or a redirect is in progress.

import { Spinner } from "@/components/shared/spinner";
import { UiMessage } from "@/constants/ui-messages";
import { ComponentSize } from "@/enums/ui/component-size";

export function PageLoader() {
  return (
    <div className="flex flex-1 items-center justify-center p-8 text-primary">
      <Spinner size={ComponentSize.Large} label={UiMessage.Loading} />
    </div>
  );
}
