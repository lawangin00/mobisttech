const KEY = "mobisttech.customer.guest-owner.v1";

export function readGuestOwnerToken(): string | null {
  if (typeof window === "undefined") return null;
  const value = window.localStorage.getItem(KEY);
  return value && /^[A-Za-z0-9_-]{32,128}$/.test(value) ? value : null;
}

export function ensureGuestOwnerToken(): string {
  const current = readGuestOwnerToken();
  if (current) return current;
  const value = crypto.randomUUID().replaceAll("-", "") + crypto.randomUUID().replaceAll("-", "");
  window.localStorage.setItem(KEY, value);
  return value;
}

export function clearGuestOwnerToken() {
  if (typeof window !== "undefined") window.localStorage.removeItem(KEY);
}
