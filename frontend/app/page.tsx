'use client';

import { useEffect, useState } from 'react';

type Table = {
  id: number;
  table_number: string;
  name: string;
  price: number;
  status: 'available' | 'payment_pending' | 'playing' | 'offline';
};

type PaymentMethod = 'QR' | 'MANUAL_MPESA';

type Session = {
  session_id: string;
  table_number: string;
  amount: number;
  payment_method: PaymentMethod;
  account_reference: string;
  paybill: string;
  status: string;
  expires_at_minutes: number;
};

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

const statusText: Record<Table['status'], string> = {
  available: 'Available',
  payment_pending: 'Payment pending',
  playing: 'In use',
  offline: 'Offline',
};

export default function Home() {
  const [tables, setTables] = useState<Table[]>([]);
  const [selected, setSelected] = useState<Table | null>(null);
  const [method, setMethod] = useState<PaymentMethod>('MANUAL_MPESA');
  const [phone, setPhone] = useState('');
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');

  async function loadTables() {
    try {
      const response = await fetch(`${apiBase}/tables`, { cache: 'no-store' });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error ?? 'Unable to load tables.');
      setTables(payload.data ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load tables.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadTables();
    const timer = window.setInterval(() => void loadTables(), 15000);
    return () => window.clearInterval(timer);
  }, []);

  async function createPayment() {
    if (!selected) return;
    setCreating(true);
    setError('');

    try {
      const response = await fetch(`${apiBase}/payments`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          table_id: selected.id,
          payment_method: method,
          phone_number: phone.trim() || null,
        }),
      });

      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error ?? 'Unable to create payment session.');

      setSession(payload.data);
      void loadTables();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to create payment session.');
    } finally {
      setCreating(false);
    }
  }

  useEffect(() => {
    if (!session?.session_id) return;

    let active = true;
    const check = async () => {
      try {
        const response = await fetch(`${apiBase}/payments/session/${session.session_id}`, { cache: 'no-store' });
        const payload = await response.json();
        if (!active || !response.ok) return;

        if (payload.data?.status === 'completed') {
          setSession((current) => current ? { ...current, status: 'completed' } : current);
          void loadTables();
        }
      } catch {
        // Background status polling should not interrupt the payment instructions.
      }
    };

    const timer = window.setInterval(check, 4000);
    void check();

    return () => {
      active = false;
      window.clearInterval(timer);
    };
  }, [session?.session_id]);

  function selectTable(table: Table) {
    if (table.status !== 'available') return;
    setSelected(table);
    setSession(null);
    setError('');
  }

  return (
    <main className="shell">
      <div className="container">
        <header className="header">
          <div className="brand">Pool Management System</div>
          <div className="badge">Secure payment verification</div>
        </header>

        <section className="hero">
          <p className="eyebrow">PLAY • PAY • UNLOCK</p>
          <h1>Choose your pool table</h1>
          <p>Pay with M-Pesa. A table is unlocked only after the payment is confirmed by the system.</p>
        </section>

        {error && <p className="error" role="alert">{error}</p>}

        {loading ? (
          <div className="loading">Loading tables…</div>
        ) : (
          <section className="grid" aria-label="Pool tables">
            {tables.map((table) => (
              <article className={`card status-${table.status}`} key={table.id}>
                <div>
                  <p className="table-code">{table.table_number}</p>
                  <h2>{table.name}</h2>
                  <div className="price">KSh {Number(table.price).toLocaleString()}</div>
                  <span className="status">{statusText[table.status]}</span>
                </div>
                <button
                  className="button"
                  type="button"
                  disabled={table.status !== 'available'}
                  onClick={() => selectTable(table)}
                >
                  {table.status === 'available' ? 'Start payment' : statusText[table.status]}
                </button>
              </article>
            ))}
          </section>
        )}

        {selected && (
          <section className="payment-panel" aria-label="Payment options">
            <div className="panel-header">
              <div>
                <p className="eyebrow">{selected.name}</p>
                <h2>Pay KSh {Number(selected.price).toLocaleString()}</h2>
              </div>
              <button
                className="close"
                type="button"
                onClick={() => { setSelected(null); setSession(null); }}
                aria-label="Close"
              >
                ×
              </button>
            </div>

            <div className="method-grid">
              <button className={`method ${method === 'QR' ? 'active' : ''}`} onClick={() => setMethod('QR')} type="button">
                <strong>Smartphone / QR</strong>
                <span>Smartphone-friendly payment flow.</span>
              </button>
              <button className={`method ${method === 'MANUAL_MPESA' ? 'active' : ''}`} onClick={() => setMethod('MANUAL_MPESA')} type="button">
                <strong>Manual M-Pesa</strong>
                <span>Use SIM Toolkit or USSD. No smartphone required.</span>
              </button>
            </div>

            {!session ? (
              <>
                {method === 'MANUAL_MPESA' && (
                  <div className="instructions">
                    <strong>Manual M-Pesa</strong>
                    <ol>
                      <li>Start the payment session below.</li>
                      <li>Open M-Pesa and choose <b>Lipa na M-Pesa → Pay Bill</b>.</li>
                      <li>Enter the venue PayBill number shown after the session starts.</li>
                      <li>Use the exact payment reference shown after the session starts.</li>
                      <li>Enter the displayed amount and your M-Pesa PIN.</li>
                    </ol>
                  </div>
                )}

                {method === 'QR' && (
                  <div className="instructions">
                    <strong>Smartphone / QR</strong>
                    <p>The payment session is created first. The production Dynamic QR integration will be enabled when the client's Daraja credentials are configured.</p>
                  </div>
                )}

                <label className="field">
                  <span>Phone number (optional)</span>
                  <input
                    value={phone}
                    onChange={(event) => setPhone(event.target.value)}
                    placeholder="07XX XXX XXX"
                    inputMode="tel"
                    autoComplete="tel"
                  />
                </label>

                <button className="button" disabled={creating} onClick={createPayment} type="button">
                  {creating ? 'Creating secure session…' : 'Create payment session'}
                </button>
              </>
            ) : (
              <div className={`payment-state ${session.status === 'completed' ? 'success' : ''}`}>
                <p className="eyebrow">{session.status === 'completed' ? 'PAYMENT CONFIRMED' : 'PAYMENT SESSION ACTIVE'}</p>
                <h3>{session.status === 'completed' ? 'Table unlocked' : 'Complete your M-Pesa payment'}</h3>

                {session.status !== 'completed' && (
                  <>
                    {method === 'MANUAL_MPESA' && (
                      <div className="instructions">
                        <strong>PayBill</strong>
                        <p>Business number: <b>{session.paybill || 'Provided by the pool operator'}</b></p>
                        <p>Account/reference: <b>{session.account_reference}</b></p>
                        <p>Amount: <b>KSh {Number(session.amount).toLocaleString()}</b></p>
                      </div>
                    )}

                    {method === 'QR' && (
                      <div className="instructions">
                        <strong>QR session ready</strong>
                        <p>Dynamic QR generation is awaiting the client's live Daraja configuration. Do not treat a screenshot or customer message as proof of payment.</p>
                      </div>
                    )}

                    <p className="waiting">Waiting for verified M-Pesa confirmation…</p>
                  </>
                )}

                {session.status === 'completed' && (
                  <p className="success-text">The backend has confirmed the payment and queued the table unlock.</p>
                )}
              </div>
            )}
          </section>
        )}

        <footer className="footer">M-Pesa payments are verified server-side before table control is triggered.</footer>
      </div>
    </main>
  );
}
