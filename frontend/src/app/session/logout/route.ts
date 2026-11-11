import { NextResponse } from "next/server";
import { backendApiUrl, clearSession, getToken } from "@/lib/server/session";

export const dynamic = "force-dynamic";

export async function POST() {
  const token = await getToken();

  if (token) {
    try {
      await fetch(backendApiUrl("auth/logout"), {
        method: "POST",
        headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      });
    } catch {
      // Ignore network errors on logout — we clear the local session regardless.
    }
  }

  await clearSession();
  return NextResponse.json({ message: "Logged out." });
}
