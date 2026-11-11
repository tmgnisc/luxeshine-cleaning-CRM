import { NextRequest, NextResponse } from "next/server";

const TOKEN_COOKIE = "dtd_token";
const ROLE_COOKIE = "dtd_role";

const ADMIN_ROLES = ["admin", "super_admin"];

function homeForRole(role?: string): string {
  if (role && ADMIN_ROLES.includes(role)) return "/admin";
  if (role === "cleaner") return "/cleaner";
  if (role === "customer") return "/account";
  return "/";
}

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const token = request.cookies.get(TOKEN_COOKIE)?.value;
  const role = request.cookies.get(ROLE_COOKIE)?.value;

  // Internal tool — the root is not a marketing page. Go straight to the
  // dashboard when signed in, otherwise to login.
  if (pathname === "/") {
    const dest = token && role ? homeForRole(role) : "/login";
    const url = request.nextUrl.clone();
    url.pathname = dest === "/" ? "/login" : dest;
    url.search = "";
    return NextResponse.redirect(url);
  }

  const needsAdmin = pathname.startsWith("/admin");
  const needsCleaner = pathname.startsWith("/cleaner");
  const needsCustomer = pathname.startsWith("/account");
  const isProtected = needsAdmin || needsCleaner || needsCustomer;

  if (!isProtected) {
    return NextResponse.next();
  }

  if (!token) {
    const url = request.nextUrl.clone();
    url.pathname = "/login";
    url.searchParams.set("next", pathname);
    return NextResponse.redirect(url);
  }

  const roleAllowed =
    (needsAdmin && role && ADMIN_ROLES.includes(role)) ||
    (needsCleaner && role === "cleaner") ||
    (needsCustomer && role === "customer");

  if (!roleAllowed) {
    const url = request.nextUrl.clone();
    url.pathname = homeForRole(role);
    return NextResponse.redirect(url);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/", "/admin/:path*", "/cleaner/:path*", "/account/:path*"],
};
