'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';

type Payment = {
  id: number;
  table_id: number;
  table_number?: string;
  table_name?: string;
  payment_method?: string;
  phone_number?: string | null;
  amount: string | number;
  account_reference?: string | null;
  status: string;
  transaction_id?: string | null;
  mpesa_receipt?: string | null;
  created_at?: string | null;
  paid_at?: string | null;
};

const money = (value: string | number) => Number(value || 0).toLocaleString('en-KE', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const dateLabel = (value?: string | null) => {
  if (!value) return '—';
  const date = new Date(value.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('en-KE');
};

export default function PaymentsPage() {
  const router = useRouter();
  const [payments, setPayments] = useState<Payment[]>([]);
  const [user, setUser] = useState<{ name?: string; role?: string } | null>(null);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    const api = process.env.NEXT_PUBLIC_API_URL;
    if (!api) { setError('API URL is not configured.'); setLoading(false); return; }
    try {
      const response = await fetch(`${api}/payments`, { credentials: 'include', cache: 'no-store' });
      if (response.status === 401) { router.replace('/login'); return; }
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to load payments.');
      setPayments(result.data ?? []);
      setError('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unable to connect to the API.');
    } finally { setLoading(false); }
  }, [router]);

  useEffect(() => {
    const api = process.env.NEXT_PUBLIC_API_URL;
    if (!api) { setLoading(false); setError('API URL is not configured.'); return; }
    fetch(`${api}/auth/me`, { credentials: 'include', cache: 'no-store' })
      .then(async response => {
        if (!response.ok) { router.replace('/login'); return; }
        const result = await response.json();
        setUser(result.data ?? null);
        await load();
      })
      .catch(() => { setError('Unable to verify your session.'); setLoading(false); });
  }, [load, router]);

  const filtered = useMemo(() => payments.filter(payment => {
    const matchesStatus = status === 'all' || payment.status.toLowerCase() === status;
    const haystack = [payment.table_number, payment.table_name, payment.account_reference,
      payment.transaction_id, payment.mpesa_receipt, payment.phone_number, payment.id]
      .filter(Boolean).join(' ').toLowerCase();
    return matchesStatus && haystack.includes(search.toLowerCase().trim());
  }), [payments, search, status]);

  const confirmedTotal = filtered.filter(p => p.status === 'confirmed')
    .reduce((sum, p) => sum + Number(p.amount || 0), 0);

  return (
    <main className="management-page">
      <header className="management-header">
        <div>
          <Link href="/dashboard">← Back to overview</Link>
          <span>POOLPILOT / PAYMENTS</span>
          <h1>Payments</h1>
          <p>Review payment records and verify the status reported by the payment integration.</p>
        </div>
        <div className="role-badge">{user?.name ?? 'Signed in'}</div>
      </header>

      <section className="stats-grid" style={{ maxWidth: 1280, margin: '0 auto 20px' }}>
        <div className="stat-card"><div className="stat-icon blue">↗</div><div><span>All records</span><strong>{payments.length}</strong><small>Payments in this account</small></div></div>
        <div className="stat-card"><div className="stat-icon green">✓</div><div><span>Confirmed</span><strong>{payments.filter(p => p.status === 'confirmed').length}</strong><small>Successful payment records</small></div></div>
        <div className="stat-card"><div className="stat-icon amber">◷</div><div><span>Pending</span><strong>{payments.filter(p => p.status === 'pending').length}</strong><small>Awaiting verified notification</small></div></div>
        <div className="stat-card revenue"><div className="stat-icon purple">KSh</div><div><span>Confirmed in view</span><strong>KSh {money(confirmedTotal)}</strong><small>Filtered confirmed payments</small></div></div>
      </section>

      <section className="detail-card" style={{ maxWidth: 1280, margin: '0 auto' }}>
        <div className="detail-card-heading">
          <div><span className="detail-eyebrow">TRANSACTION REGISTER</span><h2>Payment history</h2></div>
          <button className="ghost-button" onClick={() => { setLoading(true); void load(); }}>Refresh ↻</button>
        </div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, marginTop: 18 }}>
          <input aria-label="Search payments" value={search} onChange={e => setSearch(e.target.value)}
            placeholder="Search table, T1 reference, receipt…" style={{ flex: '1 1 240px', minWidth: 0, border: '1px solid #dfe5ed', borderRadius: 10, padding: '11px 12px', font: 'inherit', fontSize: 11 }} />
          <select aria-label="Filter payment status" value={status} onChange={e => setStatus(e.target.value)}
            style={{ border: '1px solid #dfe5ed', borderRadius: 10, padding: '11px 12px', background: '#fff', font: 'inherit', fontSize: 11 }}>
            <option value="all">All statuses</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="failed">Failed</option>
          </select>
        </div>
        {error && <div className="alert" style={{ marginTop: 16 }}>{error}</div>}
        {loading ? <div className="loading-card" style={{ marginTop: 16 }}>Loading payments…</div> : (
          <div style={{ overflowX: 'auto', marginTop: 16 }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 760, fontSize: 11 }}>
              <thead><tr style={{ textAlign: 'left', color: '#8490a0', fontSize: 9 }}>
                {['Payment', 'Table / account', 'Amount', 'Status', 'Transaction', 'Created / paid'].map(label => <th key={label} style={{ padding: '12px 10px', borderBottom: '1px solid #e8ecf1' }}>{label}</th>)}
              </tr></thead>
              <tbody>
                {filtered.map(payment => (
                  <tr key={payment.id}>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}><strong>#{payment.id}</strong><div style={{ color: '#8b95a2', fontSize: 9, marginTop: 4 }}>{payment.payment_method ?? 'Paybill'}</div></td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}><strong>{payment.table_name ?? payment.table_number ?? `Table ${payment.table_id}`}</strong><div style={{ color: '#8b95a2', fontSize: 9, marginTop: 4 }}>Account: {payment.account_reference || 'Not recorded'}</div></td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4', whiteSpace: 'nowrap' }}>KSh {money(payment.amount)}</td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}><span className={`status ${payment.status}`}>{payment.status}</span></td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}>{payment.transaction_id ?? payment.mpesa_receipt ?? '—'}</td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4', color: '#7d889a', fontSize: 9 }}>{dateLabel(payment.created_at)}<div style={{ marginTop: 4 }}>Paid: {dateLabel(payment.paid_at)}</div></td>
                  </tr>
                ))}
              </tbody>
            </table>
            {!filtered.length && <div className="detail-empty"><strong>No matching payments</strong><span>Try a different search or status filter.</span></div>}
          </div>
        )}
        <p style={{ color: '#8b95a2', fontSize: 9, lineHeight: 1.6, marginTop: 18 }}>This page is read-only. Payments are confirmed by the configured backend integration; this screen cannot manually mark a payment as paid or unlock a table.</p>
      </section>
    </main>
  );
}
