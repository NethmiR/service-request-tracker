// Accessibility attributes that a form field passes to the control it wraps.
// Links the control to its label, hint and error message.

export interface FormControlProps {
  id: string;
  required?: boolean;
  "aria-describedby"?: string;
  "aria-invalid"?: true;
}
