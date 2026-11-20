// Root layout shared by every page, holding the fonts, metadata and app providers.
// Renders every page dynamically so each response receives its own CSP nonce.

import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { connection } from "next/server";
import { AppProviders } from "@/components/shared/app-providers";
import { APPLICATION_DESCRIPTION, APPLICATION_NAME } from "@/constants/application";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: APPLICATION_NAME,
  description: APPLICATION_DESCRIPTION,
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  await connection();

  return (
    <html
      lang="en"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col">
        <AppProviders>{children}</AppProviders>
      </body>
    </html>
  );
}
