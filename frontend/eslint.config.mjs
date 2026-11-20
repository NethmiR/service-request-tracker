// ESLint configuration for the frontend, built on the Next.js presets.
// Adds rules that enforce the type safety and frontend security standards automatically.

import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const browserStorageMessage =
  "Browser storage is not allowed. Keep data in server state or component state.";

const browserStorageNames = ["localStorage", "sessionStorage"];

const browserStorageOwners = ["window", "globalThis", "self"];

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  {
    rules: {
      "@typescript-eslint/no-explicit-any": "error",
      "@typescript-eslint/consistent-type-imports": "error",
      "react/no-danger": "error",
      "no-restricted-syntax": [
        "error",
        {
          selector: "TSUnknownKeyword",
          message:
            "Do not write unknown types. Use a specific type or narrow with instanceof ApiError.",
        },
      ],
      "no-restricted-globals": [
        "error",
        ...browserStorageNames.map((name) => ({
          name,
          message: browserStorageMessage,
        })),
      ],
      "no-restricted-properties": [
        "error",
        ...browserStorageOwners.flatMap((object) =>
          browserStorageNames.map((property) => ({
            object,
            property,
            message: browserStorageMessage,
          })),
        ),
      ],
    },
  },
  globalIgnores([".next/**", "out/**", "build/**", "next-env.d.ts"]),
]);

export default eslintConfig;
