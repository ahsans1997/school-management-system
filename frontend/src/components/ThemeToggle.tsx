"use client";

import { Moon, Sun } from "lucide-react";
import { useSyncExternalStore } from "react";
import { currentTheme, setTheme, subscribeTheme } from "@/lib/theme";

/** Sun / moon button that switches between the light and dark themes. */
export function ThemeToggle() {
  // The server can't know the stored choice, so it renders light; the client
  // corrects it after hydration without a mismatch.
  const theme = useSyncExternalStore(
    subscribeTheme,
    currentTheme,
    () => "light" as const,
  );
  const next = theme === "dark" ? "light" : "dark";

  return (
    <button
      type="button"
      onClick={() => setTheme(next)}
      aria-label={`Switch to ${next} theme`}
      title={`Switch to ${next} theme`}
      className="btn btn-ghost h-9 w-9 rounded-full p-0"
    >
      {theme === "dark" ? (
        <Sun className="h-[18px] w-[18px]" />
      ) : (
        <Moon className="h-[18px] w-[18px]" />
      )}
    </button>
  );
}
