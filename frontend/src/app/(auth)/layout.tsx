import Link from "next/link";
import Image from "next/image";
import type { ReactNode } from "react";

export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-10">
      <Link href="/" className="mb-8 flex items-center" aria-label="LuxeShine Cleaning">
        <Image src="/logo.png" alt="LuxeShine Cleaning" width={500} height={235} priority className="h-14 w-auto" />
      </Link>
      <div className="w-full max-w-md">{children}</div>
      <p className="mt-8 text-xs text-muted-foreground">
        © {new Date().getFullYear()} LuxeShine Cleaning. Professional cleaning services.
      </p>
    </div>
  );
}
