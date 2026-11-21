// Returns a value only after it has stayed unchanged for the given delay.
// Used by the request search box so typing does not send one request per character.

"use client";

import { useEffect, useState } from "react";

export function useDebouncedValue<Value>(value: Value, delayMilliseconds: number): Value {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedValue(value), delayMilliseconds);

    return () => window.clearTimeout(timeout);
  }, [value, delayMilliseconds]);

  return debouncedValue;
}
