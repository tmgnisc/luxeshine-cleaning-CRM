import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { Providers } from "@/components/providers";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-sans",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "LuxeShine Cleaning — Professional Home Cleaning",
    template: "%s · LuxeShine Cleaning",
  },
  description:
    "Book trusted, vetted cleaners in minutes. Home cleaning, deep cleans, laundry and more.",
  openGraph: {
    title: "LuxeShine Cleaning",
    description: "Professional home cleaning, booked in minutes.",
    type: "website",
  },
  icons: { icon: "/logo.png" },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body className={`${inter.variable} font-sans antialiased`}>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
