import { NextRequest, NextResponse } from "next/server";
import { backendApiUrl, getToken } from "@/lib/server/session";

export const dynamic = "force-dynamic";

/**
 * Backend-for-frontend proxy. The browser calls `/bff/<path>` on the same
 * origin; this handler attaches the Bearer token from the httpOnly cookie and
 * forwards to the Laravel API, so the token is never exposed to client JS.
 */
async function handle(request: NextRequest, path: string[]): Promise<NextResponse> {
  const token = await getToken();
  const search = request.nextUrl.search;
  const url = backendApiUrl(path.join("/")) + search;

  const headers = new Headers();
  headers.set("Accept", "application/json");
  const contentType = request.headers.get("content-type");
  if (contentType) {
    headers.set("Content-Type", contentType);
  }
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const method = request.method.toUpperCase();
  const hasBody = !["GET", "HEAD"].includes(method);

  const init: RequestInit & { duplex?: "half" } = {
    method,
    headers,
    redirect: "manual",
  };

  if (hasBody) {
    init.body = await request.arrayBuffer();
  }

  let backendResponse: Response;
  try {
    backendResponse = await fetch(url, init);
  } catch {
    return NextResponse.json(
      { message: "The server is unreachable. Please try again." },
      { status: 502 },
    );
  }

  const body = await backendResponse.arrayBuffer();
  const responseHeaders = new Headers();
  const passThrough = ["content-type", "content-disposition", "x-unread-count"];
  for (const key of passThrough) {
    const value = backendResponse.headers.get(key);
    if (value) {
      responseHeaders.set(key, value);
    }
  }

  return new NextResponse(body, {
    status: backendResponse.status,
    headers: responseHeaders,
  });
}

type Ctx = { params: Promise<{ path: string[] }> };

export async function GET(request: NextRequest, ctx: Ctx) {
  return handle(request, (await ctx.params).path);
}
export async function POST(request: NextRequest, ctx: Ctx) {
  return handle(request, (await ctx.params).path);
}
export async function PUT(request: NextRequest, ctx: Ctx) {
  return handle(request, (await ctx.params).path);
}
export async function PATCH(request: NextRequest, ctx: Ctx) {
  return handle(request, (await ctx.params).path);
}
export async function DELETE(request: NextRequest, ctx: Ctx) {
  return handle(request, (await ctx.params).path);
}
