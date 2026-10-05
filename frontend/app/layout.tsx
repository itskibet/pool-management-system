import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Pool Management System',
  description: 'Pool table payments, sessions, and table control.',
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
