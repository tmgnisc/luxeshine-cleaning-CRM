import { NextRequest, NextResponse } from "next/server";
import { backendApiUrl, setSession } from "@/lib/server/session";

export const dynamic = "force-dynamic";

export async function POST(request: NextRequest) {
  const payload = await request.json();

  let res: Response;
  try {
    res = await fetch(backendApiUrl("auth/register"), {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(payload),
    });
  } catch {
    return NextResponse.json({ message: "The server is unreachable." }, { status: 502 });
  }

  const data = await res.json().catch(() => ({}));

  if (!res.ok) {
    return NextResponse.json(data, { status: res.status });
  }

  const token = data?.data?.token as string | undefined;
  const user = data?.data?.user;
  if (!token || !user) {
    return NextResponse.json({ message: "Unexpected response from server." }, { status: 502 });
  }

  await setSession(token, user.role);
  return NextResponse.json({ data: { user } }, { status: 201 });
}
