/**
 * Light / dark theme. Light by default; a person's choice is remembered in
 * this browser and applied as a `dark` class on <html> (globals.css keys the
 * `dark:` variant off it, on screen only).
 */
export type Theme = "light" | "dark";

const STORAGE_KEY = "sms-theme";

/**
 * Runs before first paint (inline in the root layout) so a dark-mode user
 * never sees a flash of the light theme.
 */
export const THEME_INIT_SCRIPT = `try{if(localStorage.getItem("${STORAGE_KEY}")==="dark")document.documentElement.classList.add("dark")}catch(e){}`;

const listeners = new Set<() => void>();

export function currentTheme(): Theme {
  return document.documentElement.classList.contains("dark") ? "dark" : "light";
}

export function setTheme(theme: Theme): void {
  document.documentElement.classList.toggle("dark", theme === "dark");
  try {
    localStorage.setItem(STORAGE_KEY, theme);
  } catch {
    // Private mode or blocked storage: the choice lasts for this page only.
  }
  listeners.forEach((notify) => notify());
}

/** For useSyncExternalStore. */
export function subscribeTheme(notify: () => void): () => void {
  listeners.add(notify);
  return () => listeners.delete(notify);
}
