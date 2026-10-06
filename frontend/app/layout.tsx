import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'PoolPilot — Pool Management',
  description: 'Pool table payments and management dashboard.',
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
