export type CustomerAccount = {
  id: string;
  name: string;
  email: string;
  mobile: string;
  session_policy: {
    inactivity_minutes?: number;
    remember_enabled?: boolean;
    remember_minutes?: number;
    recent_auth_minutes?: number;
  };
  session_state: Record<string, unknown>;
};

type Envelope<T> = { data: T };

let csrfToken: string | null = null;

async function readEnvelope<T>(response: Response): Promise<T> {
  const body = (await response.json().catch(() => ({}))) as Partial<Envelope<T>> & {
    message?: string;
    errors?: Record<string, string[]>;
  };
  if (!response.ok) {
    const error = Object.values(body.errors ?? {}).flat()[0] ?? body.message ?? "Request failed.";
    throw new Error(error);
  }
  if (!("data" in body)) throw new Error("Customer API envelope is invalid.");
  return body.data as T;
}

export async function ensureCustomerCsrf(): Promise<string> {
  if (csrfToken) return csrfToken;
  const response = await fetch("/api/customer/auth/csrf-cookie", {
    method: "GET",
    credentials: "same-origin",
    cache: "no-store",
  });
  const data = await readEnvelope<{ csrf_token: string }>(response);
  csrfToken = data.csrf_token;
  return csrfToken;
}

export async function customerRequest<T>(
  path: string,
  init: RequestInit & { idempotencyKey?: string } = {},
): Promise<T> {
  const method = (init.method ?? "GET").toUpperCase();
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (!["GET", "HEAD"].includes(method)) {
    headers.set("X-CSRF-TOKEN", await ensureCustomerCsrf());
    if (init.body && !headers.has("Content-Type")) headers.set("Content-Type", "application/json");
  }
  if (init.idempotencyKey) headers.set("Idempotency-Key", init.idempotencyKey);
  const response = await fetch(`/api/customer/${path.replace(/^\/+/, "")}`, {
    ...init,
    method,
    headers,
    credentials: "same-origin",
    cache: "no-store",
  });
  if (response.status === 419) csrfToken = null;
  return readEnvelope<T>(response);
}

export function clearCustomerCsrf() {
  csrfToken = null;
}
