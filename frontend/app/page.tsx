'use client';

import { useState } from 'react';

const tables = [
  { id: 1, code: 'TABLE01', name: 'Table 01', price: 30 },
  { id: 2, code: 'TABLE02', name: 'Table 02', price: 30 },
  { id: 3, code: 'TABLE03', name: 'Table 03', price: 30 },
  { id: 4, code: 'TABLE04', name: 'Table 04', price: 30 },
  { id: 5, code: 'TABLE05', name: 'Table 05', price: 30 },
];

const apiBase = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

type PaymentMethod = 'QR' | 'MANUAL_MPESA';

export default function Home() {
  const [selected, setSelected] = useState<(typeof tables)[number] | null>(null);
  const [method, setMethod] = useState<PaymentMethod>('QR');
  const [phone, setPhone] = useState('');
  const [loading, setLoading] = useState(false);
  const [reference, setReference] = useState('');
  const [error, setError] = useState('');

  async function createPayment() {
    if (!selected) return;
    setLoading(true);
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

      setReference(payload.data.account_reference);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to create payment session.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="shell">
      <div className="container">
        <header className="header">
          <div className="brand">Pool Management System</div>
          <div className="badge">Verified payment flow</div>
        </header>

        <section className="hero">
          <p className="eyebrow">PLAY • PAY • UNLOCK</p>
          <h1>Choose your pool table</h1>
          <p>Use QR payment if you have a smartphone, or use normal M-Pesa SIM Toolkit/USSD if you do not.</p>
        </section>

        <section className="grid" aria-label="Pool tables">
          {tables.map((table) => (
            <article className="card" key={table.code}>
              <div>
                <p className="table-code">{table.code}</p>
                <h2>{table.name}</h2>
                <div className="price">KSh {table.price}</div>
              </div>
              <button className="button" type="button" onClick={() => { setSelected(table); setReference(''); setError(''); }}>
                Start payment
              </button>
            </article>
          ))}
        </section>

        {selected && (
          <section className="payment-panel" aria-label="Payment options">
            <div className="panel-header">
              <div>
                <p className="eyebrow">{selected.name}</p>
                <h2>Pay KSh {selected.price}</h2>
              </div>
              <button className="close" type="button" onClick={() => setSelected(null)} aria-label="Close">×</button>
            </div>

            <div className="method-grid">
              <button className={`method ${method === 'QR' ? 'active' : ''}`} onClick={() => setMethod('QR')} type="button">
                <strong>Smartphone / QR</strong>
                <span>Scan the table QR and pay.</span>
              </button>
              <button className={`method ${method === 'MANUAL_MPESA' ? 'active' : ''}`} onClick={() => setMethod('MANUAL_MPESA')} type="button">
                <strong>Manual M-Pesa</strong>
                <span>Use SIM Toolkit or USSD. No smartphone required.</span>
              </button>
            </div>

            {method === 'MANUAL_MPESA' && (
              <div className="instructions">
                <strong>Manual payment</strong>
                <ol>
                  <li>Open M-Pesa on your phone.</li>
                  <li>Select <b>Lipa na M-Pesa → Pay Bill</b>.</li>
                  <li>Enter the venue business number supplied by the pool operator.</li>
                  <li>Use the payment reference shown after you start the session.</li>
                  <li>Enter KSh {selected.price} and your M-Pesa PIN.</li>
                </ol>
              </div>
            )}

            {method === 'QR' && (
              <div className="instructions">
                <strong>QR payment</strong>
                <p>The live QR will be generated from this payment session when the M-Pesa integration is connected.</p>
              </div>
            )}

            <label className="field">
              <span>Phone number (optional)</span>
              <input value={phone} onChange={(event) => setPhone(event.target.value)} placeholder="07XX XXX XXX" inputMode="tel" />
            </label>

            <button className="button primary" disabled={loading} onClick={createPayment} type="button">
              {loading ? 'Creating payment session…' : 'Continue'}
            </button>

            {reference && (
              <div className="reference" role="status">
                <span>Payment reference</span>
                <strong>{reference}</strong>
                <small>Keep this reference. The table unlocks only after the backend confirms the M-Pesa transaction.</small>
              </div>
            )}
            {error && <p className="error" role="alert">{error}</p>}
          </section>
        )}

        <footer className="footer">Payments are verified by the backend before a table can be unlocked.</footer>
      </div>
    </main>
  );
}
