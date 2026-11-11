import { cookies } from "next/headers";

export const TOKEN_COOKIE = "dtd_token";
export const ROLE_COOKIE = "dtd_role";

export const BACKEND_URL = process.env.BACKEND_URL ?? "http://localhost:8000";
export const BACKEND_API_PREFIX = process.env.BACKEND_API_PREFIX ?? "/api/v1";

export function backendApiUrl(path: string): string {
  const clean = path.replace(/^\/+/, "");
  return `${BACKEND_URL}${BACKEND_API_PREFIX}/${clean}`;
}

export async function getToken(): Promise<string | undefined> {
  const store = await cookies();
  return store.get(TOKEN_COOKIE)?.value;
}

export async function getRole(): Promise<string | undefined> {
  const store = await cookies();
  return store.get(ROLE_COOKIE)?.value;
}

export async function setSession(token: string, role: string): Promise<void> {
  const store = await cookies();
  const secure = process.env.NODE_ENV === "production";
  const common = {
    httpOnly: true,
    secure,
    sameSite: "lax" as const,
    path: "/",
    maxAge: 60 * 60 * 24 * 14, // 14 days
  };
  store.set(TOKEN_COOKIE, token, common);
  store.set(ROLE_COOKIE, role, common);
}

export async function clearSession(): Promise<void> {
  const store = await cookies();
  store.delete(TOKEN_COOKIE);
  store.delete(ROLE_COOKIE);
}
