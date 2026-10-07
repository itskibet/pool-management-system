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
  payment_type: 'paybill' | 'till' | string;
  paybill_number: string;
  account_prefix: string;
};

type Payment = {
  id: number;
  table_id: number;
  payment_method: string;
  phone_number: string | null;
  amount: string;
  account_reference: string | null;
  status: string;
  mpesa_receipt?: string | null;
  transaction_id?: string | null;
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
        fetch(`${apiUrl}/tables/${id}`, { credentials: 'include' }).then((r) => {
          if (!r.ok) throw new Error('Table not found');
          return r.json();
        }),
        fetch(`${apiUrl}/payments`, { credentials: 'include' }).then((r) => r.ok ? r.json() : { data: [] }),
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
  const accountReference = table.table_number.replace(/_/g, '');

  return (
    <main className="detail-page">
      <header className="detail-header">
        <div>
          <Link href="/" className="back-link">← Back to overview</Link>
          <div className="detail-kicker">TABLE MANAGEMENT <span>/</span> {table.table_number.replace('_', ' ')}</div>
          <div className="detail-title-row">
            <div>
              <h1>{table.name}</h1>
              <p>Payment is made directly from the customer&apos;s M-Pesa menu.</p>
            </div>
            <span className={`detail-status ${table.status}`}><i />{statusLabel[table.status] ?? table.status}</span>
          </div>
        </div>
      </header>

      <section className="detail-grid">
        <div className="detail-main">
          <div className="detail-card table-summary-card">
            <div className="detail-card-heading"><div><span className="detail-eyebrow">TABLE</span><h2>{table.table_number.replace('_', ' ')}</h2></div><span className="rate-pill">KSh {table.price} / game</span></div>
            <div className="large-pool-visual"><div className="pool-rails"><span /><span /><span /><span /><span /></div><div className="pool-pocket p1"/><div className="pool-pocket p2"/><div className="pool-pocket p3"/><div className="pool-pocket p4"/><div className="pool-pocket p5"/><div className="pool-pocket p6"/></div>
            <div className="session-state"><div><span>Current session</span><strong>{table.status === 'playing' ? 'Active game' : table.status === 'payment_pending' ? 'Awaiting payment' : 'No active game'}</strong></div><div><span>Unlock</span><strong>{table.status === 'playing' ? 'Unlock queued' : 'Waiting for verified payment'}</strong></div></div>
          </div>

          <div className="detail-card action-card">
            <span className="detail-eyebrow">CUSTOMER PAYMENT</span>
            <h2>Pay directly with M-Pesa</h2>
            <p>The customer does not need to scan a QR code or enter a phone number into this system.</p>
            <div className="payment-instructions">
              <div><span>1</span><div><strong>Open M-Pesa</strong><small>Choose Lipa na M-Pesa → Pay Bill.</small></div></div>
              <div><span>2</span><div><strong>Business number</strong><small>{table.paybill_number}</small></div></div>
              <div><span>3</span><div><strong>Account number</strong><small>{accountReference}</small></div></div>
              <div><span>4</span><div><strong>Amount</strong><small>KSh {table.price}</small></div></div>
              <div><span>5</span><div><strong>Complete payment</strong><small>After M-Pesa confirms it, the system verifies the transaction.</small></div></div>
            </div>
            <div className="security-note"><span>✓</span><div><strong>Verified payment unlocks the table</strong><small>The software never unlocks from a customer&apos;s claim or a client-side button.</small></div></div>
          </div>

          <div className="detail-card">
            <div className="detail-card-heading"><div><span className="detail-eyebrow">PAYMENT ACTIVITY</span><h2>Table payments</h2></div><span className="count-pill">{payments.length} total</span></div>
            {payments.length === 0 ? (
              <div className="detail-empty"><div className="detail-empty-icon">KSh</div><strong>No payments for this table</strong><span>Confirmed M-Pesa transactions will appear here.</span></div>
            ) : (
              <div className="payment-list">
                {payments.slice(0, 6).map((payment) => (
                  <div className="payment-row" key={payment.id}>
                    <div className="payment-icon">KSh</div>
                    <div className="payment-info"><strong>{payment.transaction_id || payment.account_reference || 'Payment'}</strong><span>M-Pesa Paybill • {payment.account_reference || accountReference}</span></div>
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
            <h2>Game session</h2>
            <p>A confirmed Paybill payment creates one game session. There is no automatic time limit.</p>
            <button className="detail-primary" disabled>{table.status === 'playing' ? 'Game in progress' : 'Starts after payment verification'}</button>
            <div className="security-note"><span>✓</span><div><strong>Manual game ending</strong><small>An attendant ends the game; duration is recorded for reporting only.</small></div></div>
          </div>

          <div className="detail-card">
            <div className="detail-card-heading"><div><span className="detail-eyebrow">CONTROLLER</span><h2>Connection</h2></div><span className="controller-badge"><i /> Ready</span></div>
            <div className="controller-row"><span>MQTT topic</span><strong>{table.mqtt_topic}</strong></div>
            <div className="controller-row"><span>Hardware state</span><strong>External controller</strong></div>
            <div className="controller-row"><span>Unlock policy</span><strong>Confirmed Paybill only</strong></div>
          </div>

          <div className="detail-card">
            <div className="detail-card-heading"><div><span className="detail-eyebrow">PAYMENT QUEUE</span><h2>Verification</h2></div></div>
            <div className="queue-stat"><strong>{pending.length}</strong><span>Pending payments</span></div>
            <div className="queue-stat confirmed-stat"><strong>{confirmed.length}</strong><span>Confirmed payments</span></div>
            <div className="verification-flow"><span>M-Pesa</span><b>→</b><span>Verify</span><b>→</b><span>Unlock</span></div>
          </div>
        </aside>
      </section>
    </main>
  );
}
