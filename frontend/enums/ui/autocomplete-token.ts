// Autocomplete tokens for the credential inputs, so password managers behave correctly.
// Off is used for values such as a temporary password that must never be saved or suggested.
// Used only by the frontend.

export enum AutocompleteToken {
  Username = "username",
  CurrentPassword = "current-password",
  NewPassword = "new-password",
  Off = "off",
}
