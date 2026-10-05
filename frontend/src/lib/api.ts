// Thin fetch wrapper around the Laravel backend. Every call goes through
// `apiFetch`, which attaches the bearer token (read from localStorage — see
// auth-context.tsx, the only place that writes it) and the active
// branch/warehouse scoping headers, and normalizes error responses into an
// `ApiError` the UI can render consistently (field errors, business-rule
// errors, or a plain message).

const API_BASE = (
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api"
).replace(/\/$/, "");

const TOKEN_KEY = "pharma_erp_token";
const SCOPE_KEY = "pharma_erp_scope";
const USER_KEY = "pharma_erp_user";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null) {
  if (typeof window === "undefined") return;
  if (token) window.localStorage.setItem(TOKEN_KEY, token);
  else window.localStorage.removeItem(TOKEN_KEY);
}

export function getCachedUser(): any | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = window.localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function setCachedUser(user: unknown | null) {
  if (typeof window === "undefined") return;
  if (user) window.localStorage.setItem(USER_KEY, JSON.stringify(user));
  else window.localStorage.removeItem(USER_KEY);
}

export interface Scope {
  branch_id: string | null;
  warehouse_id: string | null;
}

export function getScope(): Scope {
  if (typeof window === "undefined")
    return { branch_id: null, warehouse_id: null };
  try {
    const raw = window.localStorage.getItem(SCOPE_KEY);
    if (!raw) return { branch_id: null, warehouse_id: null };
    return JSON.parse(raw) as Scope;
  } catch {
    return { branch_id: null, warehouse_id: null };
  }
}

export function setScope(scope: Scope) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(SCOPE_KEY, JSON.stringify(scope));
}

export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]> | null;
  slug: string | null;
  context: Record<string, unknown> | null;

  constructor(
    message: string,
    status: number,
    errors: Record<string, string[]> | null = null,
    slug: string | null = null,
    context: Record<string, unknown> | null = null,
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
    this.slug = slug;
    this.context = context;
  }

  /** First validation message across all fields, or the top-level message — good enough for a toast. */
  get summary(): string {
    if (this.errors) {
      const first = Object.values(this.errors)[0]?.[0];
      if (first) return first;
    }
    return this.message;
  }
}

interface RequestOptions {
  method?: "GET" | "POST" | "PUT" | "DELETE";
  body?: unknown;
  query?: Record<string, string | number | boolean | undefined | null>;
  /** Skip attaching X-Branch-Id/X-Warehouse-Id (rarely needed). */
  noScope?: boolean;
}

function buildUrl(path: string, query?: RequestOptions["query"]): string {
  const cleanPath = path.startsWith("/") ? path : `/${path}`;
  const base = API_BASE.startsWith("http")
    ? API_BASE
    : typeof window !== "undefined"
    ? `${window.location.origin}${API_BASE.startsWith("/") ? "" : "/"}${API_BASE}`
    : `http://127.0.0.1:3000${API_BASE.startsWith("/") ? "" : "/"}${API_BASE}`;

  const url = new URL(`${base}${cleanPath}`);
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value === undefined || value === null || value === "") continue;
      url.searchParams.set(key, String(value));
    }
  }
  return url.toString();
}

export async function apiFetch<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const { method = "GET", body, query, noScope } = options;

  const headers: Record<string, string> = {
    Accept: "application/json",
  };

  const token = getToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  if (!noScope) {
    const scope = getScope();
    if (scope.branch_id) headers["X-Branch-Id"] = scope.branch_id;
    if (scope.warehouse_id) headers["X-Warehouse-Id"] = scope.warehouse_id;
  }

  let payload: BodyInit | undefined;
  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
    payload = JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(buildUrl(path, query), {
      method,
      headers,
      body: payload,
    });
  } catch (err) {
    console.error("apiFetch error:", err);
    throw new ApiError(
      "Could not reach the API. Check that the backend is running and NEXT_PUBLIC_API_URL is correct.",
      0,
    );
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const text = await response.text();
  const json = text ? safeJsonParse(text) : null;

  if (!response.ok) {
    const message =
      (json && (json.message as string)) ||
      response.statusText ||
      "Request failed";
    const errors = (json && (json.errors as Record<string, string[]>)) || null;
    const slug = (json && (json.error as string)) || null;
    const context = json
      ? Object.fromEntries(
          Object.entries(json).filter(
            ([k]) => !["message", "errors", "error"].includes(k),
          ),
        )
      : null;
    throw new ApiError(message, response.status, errors, slug, context);
  }

  return json as T;
}

function safeJsonParse(text: string): Record<string, unknown> | null {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

export const api = {
  get: <T>(path: string, query?: RequestOptions["query"]) =>
    apiFetch<T>(path, { method: "GET", query }),
  post: <T>(path: string, body?: unknown) =>
    apiFetch<T>(path, { method: "POST", body }),
  put: <T>(path: string, body?: unknown) =>
    apiFetch<T>(path, { method: "PUT", body }),
  del: <T>(path: string) => apiFetch<T>(path, { method: "DELETE" }),
};
