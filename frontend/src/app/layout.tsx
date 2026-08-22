import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Code Visual Builder",
  description: "Laravel Filament Visual Project Generator",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
