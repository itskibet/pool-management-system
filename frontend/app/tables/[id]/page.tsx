'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';

type PoolTable = {
  id: number;
  table_number: string;
  name: string;
  price: string;
  status: 'available' | 'payment_pending' | 'playing' | 'offline' | string;
  mqtt_topic: string;
};

type Payment = {
  id: number;
  table_id: number;
  payment_method: string;
  phone_number: string;
  amount: string;
  account_reference: string;
  status: string;
  mpesa_receipt?: string | null;
  created_at?: string;
};

const statusLabel: Record<string, string> = {
  available: 'Available',
  playing: 'Playing',
  payment_pending: 'Payment pending',
  offline: 'Offline',
};

export default function TableDetails({ params }: { params: Promise<{ id: string }> }) {
  const [table, setTable] = useState<PoolTable | null>(null);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    params.then(({ id }) => {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL;
      if (!apiUrl) {
        setError('API URL is not configured.');
        setLoading(false);
        return;
      }

      Promise.all([
        fetch(`${apiUrl}/tables/${id}`).then((r) => {
          if (!r.ok) throw new Error('Table not found');
          return r.json();
        }),
        fetch(`${apiUrl}/payments`).then((r) => r.ok ? r.json() : { data: [] }),
      ])
        .then(([tableResult, paymentResult]) => {
          setTable(tableResult.data);
          setPayments((paymentResult.data ?? []).filter((payment: Payment) => payment.table_id === Number(id)));
        })
        .catch(() => setError('Unable to load this table. Check that the backend is running.'))
        .finally(() => setLoading(false));
    });
  }, [params]);

  if (loading) {
    return <main className="detail-page"><div className="detail-loading"><span className="loader" /> Loading table...</div></main>;
  }

  if (error || !table) {
    return (
      <main className="detail-page">
        <div className="detail-error"><strong>{error || 'Table not found.'}</strong><Link href="/">← Back to overview</Link></div>
      </main>
    );
  }

  const confirmed = payments.filter((payment) => payment.status === 'confirmed');
  const pending = payments.filter((payment) => payment.status === 'pending');

  return (
    <main className="detail-page">
      <header className="detail-header">
        <div>
          <Link href="/" className="back-link">← Back to overview</Link>
          <div className="detail-kicker">TABLE MANAGEMENT <span>/</span> {table.table_number.replace('_', ' ')}</div>
          <div className="detail-title-row">
            <div>
              <h1>{table.name}</h1>
              <p>Manage payment, session and controller state for this table.</p>
            </div>
            <span className={`detail-status ${table.status}`}><i />{statusLabel[table.status] ?? table.status}</span>
          </div>
        </div>
      </header>

      <section className="detail-grid">
        <div className="detail-main">
          <div className="detail-card table-summary-card">
            <div className="detail-card-heading"><div><span className="detail-eyebrow">TABLE</span><h2>{table.table_number.replace('_', ' ')}</h2></div><span className="rate-pill">KSh {table.price} / session</span></div>
            <div className="large-pool-visual"><div className="pool-rails"><span /><span /><span /><span /><span /></div><div className="pool-pocket p1"/><div className="pool-pocket p2"/><div className="pool-pocket p3"/><div className="pool-pocket p4"/><div className="pool-pocket p5"/><div className="pool-pocket p6"/></div>
            <div className="session-state"><div><span>Current session</span><strong>{table.status === 'playing' ? 'Active' : table.status === 'payment_pending' ? 'Awaiting payment' : 'No active session'}</strong></div><div><span>Unlock</span><strong>{table.status === 'playing' ? 'Command sent' : 'Waiting for verified payment'}</strong></div></div>
          </div>

          <div className="detail-card">
            <div className="detail-card-heading"><div><span className="detail-eyebrow">PAYMENT ACTIVITY</span><h2>Table payments</h2></div><span className="count-pill">{payments.length} total</span></div>
            {payments.length === 0 ? (
              <div className="detail-empty"><div className="detail-empty-icon">KSh</div><strong>No payments for this table</strong><span>Verified M-Pesa transactions will appear here.</span></div>
            ) : (
              <div className="payment-list">
                {payments.slice(0, 6).map((payment) => (
                  <div className="payment-row" key={payment.id}>
                    <div className="payment-icon">KSh</div>
                    <div className="payment-info"><strong>{payment.account_reference || 'Payment'}</strong><span>{payment.payment_method === 'paybill' ? 'M-Pesa Paybill' : 'M-Pesa STK Push'} • {payment.phone_number || 'Phone not supplied'}</span></div>
                    <div className="payment-amount"><strong>KSh {payment.amount}</strong><span className={`payment-status ${payment.status}`}>{payment.status}</span></div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        <aside className="detail-side">
          <div className="detail-card action-card">
            <span className="detail-eyebrow">SESSION CONTROL</span>
            <h2>Start a session</h2>
            <p>A table must have a verified payment before the real controller can be unlocked.</p>
            <button className="detail-primary" disabled={table.status !== 'available'}>{table.status === 'available' ? 'Start after payment' : 'Session unavailable'}</button>
            <div className="security-note"><span>✓</span><div><strong>Verification required</strong><small>Client-side payment claims never unlock a table.</small></div></div>
          </div>

          <div className="detail-card">
            <div className="detail-card-heading"><div><span className="detail-eyebrow">CONTROLLER</span><h2>Connection</h2></div><span className="controller-badge"><i /> Ready</span></div>
            <div className="controller-row"><span>MQTT topic</span><strong>{table.mqtt_topic}</strong></div>
            <div className="controller-row"><span>Hardware state</span><strong>External controller</strong></div>
            <div className="controller-row"><span>Unlock policy</span><strong>Verified payment only</strong></div>
          </div>

          <div className="detail-card">
            <div className="detail-card-heading"><div><span className="detail-eyebrow">PAYMENT QUEUE</span><h2>Verification</h2></div></div>
            <div className="queue-stat"><strong>{pending.length}</strong><span>Pending payments</span></div>
            <div className="queue-stat confirmed-stat"><strong>{confirmed.length}</strong><span>Confirmed payments</span></div>
            <div className="verification-flow"><span>Payment</span><b>→</b><span>Verify</span><b>→</b><span>Unlock</span></div>
          </div>
        </aside>
      </section>
    </main>
  );
}
