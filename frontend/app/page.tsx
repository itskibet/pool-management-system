'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';

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
  table_number?: string;
  table_name?: string;
  amount: string;
  status: string;
  transaction_id?: string | null;
  mpesa_receipt?: string | null;
  created_at?: string;
  paid_at?: string | null;
};

type Game = {
  id: number;
  table_id: number;
  status: string;
  started_at?: string | null;
};

const statusLabel: Record<string, string> = {
  available: 'Available',
  playing: 'Playing',
  payment_pending: 'Payment pending',
  offline: 'Offline',
};

function isToday(value?: string | null) {
  if (!value) return false;
  const date = new Date(value.replace(' ', 'T'));
  const now = new Date();
  return date.getFullYear() === now.getFullYear()
    && date.getMonth() === now.getMonth()
    && date.getDate() === now.getDate();
}

export default function Home() {
  const router = useRouter();
  const [authChecked, setAuthChecked] = useState(false);
  const [currentUser, setCurrentUser] = useState<{name:string; role:string} | null>(null);
  const [tables, setTables] = useState<PoolTable[]>([]);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [games, setGames] = useState<Game[]>([]);
  const [apiHealthy, setApiHealthy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadDashboard = useCallback(async () => {
    if (!authChecked) return;
    const apiUrl = process.env.NEXT_PUBLIC_API_URL;
    if (!apiUrl) {
      setError('API URL is not configured.');
      setLoading(false);
      return;
    }

    try {
      const [tablesResponse, paymentsResponse, gamesResponse, healthResponse] = await Promise.all([
        fetch(`${apiUrl}/tables`, { cache: 'no-store', credentials: 'include' }),
        fetch(`${apiUrl}/payments`, { cache: 'no-store', credentials: 'include' }),
        fetch(`${apiUrl}/games`, { cache: 'no-store', credentials: 'include' }),
        fetch(`${apiUrl}/health`, { cache: 'no-store' }),
      ]);

      if (!tablesResponse.ok) throw new Error('Failed to fetch tables');

      const [tableResult, paymentResult, gameResult] = await Promise.all([
        tablesResponse.json(),
        paymentsResponse.ok ? paymentsResponse.json() : { data: [] },
        gamesResponse.ok ? gamesResponse.json() : { data: [] },
      ]);

      setTables(tableResult.data ?? []);
      setPayments(paymentResult.data ?? []);
      setGames(gameResult.data ?? []);
      setApiHealthy(healthResponse.ok);
      setError('');
    } catch {
      setApiHealthy(false);
      setError('Unable to connect to the backend. Check that the API is running.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const checkAuth = async () => {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL;
      if (!apiUrl) return;
      const response = await fetch(`${apiUrl}/auth/me`, { credentials: 'include', cache: 'no-store' });
      if (!response.ok) { router.replace('/login'); return; }
      const result = await response.json();
      setCurrentUser(result.data);
      setAuthChecked(true);
    };
    checkAuth();
  }, [router]);

  useEffect(() => {
    if (!authChecked) return;
    loadDashboard();
    const timer = window.setInterval(loadDashboard, 10000);
    return () => window.clearInterval(timer);
  }, [loadDashboard, authChecked]);

  const available = useMemo(() => tables.filter((table) => table.status === 'available').length, [tables]);
  const playing = useMemo(() => tables.filter((table) => table.status === 'playing').length, [tables]);
  const pending = useMemo(() => tables.filter((table) => table.status === 'payment_pending').length, [tables]);
  const todayPayments = useMemo(
    () => payments.filter((payment) => payment.status === 'confirmed' && isToday(payment.paid_at ?? payment.created_at)),
    [payments],
  );
  const revenue = useMemo(
    () => todayPayments.reduce((total, payment) => total + Number(payment.amount || 0), 0),
    [todayPayments],
  );
  const recentPayments = payments.slice(0, 5);

  return (
    <main className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-mark"><span>8</span></div>
          <div><strong>PoolPilot</strong><span>Operations</span></div>
        </div>
        <div className="nav-label">MAIN MENU</div>
        <nav>
          <Link className="nav-item active" href="/"><span className="nav-icon">⌂</span><span>Overview</span></Link>
          <a className="nav-item" href="#tables"><span className="nav-icon">▦</span><span>Tables</span></a>
          <a className="nav-item" href="#payments"><span className="nav-icon">↗</span><span>Payments</span></a>
          <a className="nav-item" href="#games"><span className="nav-icon">◷</span><span>Games</span></a>
          <a className="nav-item" href="#health"><span className="nav-icon">▥</span><span>System</span></a>
          {(currentUser?.role === "owner" || currentUser?.role === "admin") && <Link className="nav-item" href="/users"><span className="nav-icon">♙</span><span>Users</span></Link>}
          <button className="nav-item" onClick={async () => { const apiUrl = process.env.NEXT_PUBLIC_API_URL; if (apiUrl) await fetch(`${apiUrl}/auth/logout`, { method: "POST", credentials: "include" }); router.replace("/login"); }}><span className="nav-icon">↪</span><span>Sign out</span></button>
        </nav>
        <div className="sidebar-card">
          <span className="sidebar-card-kicker">QUICK STATUS</span>
          <strong>{available} tables ready</strong>
          <p>{apiHealthy ? 'Live data is updating automatically.' : 'Backend connection needs attention.'}</p>
          <div className="mini-progress"><span style={{ width: `${tables.length ? (available / tables.length) * 100 : 0}%` }} /></div>
        </div>
        <div className="sidebar-footer">
          <div className="connection"><span className={`pulse ${apiHealthy ? '' : 'offline'}`} /> {apiHealthy ? 'API connected' : 'API offline'}</div>
          <div className="user-card"><div className="avatar">A</div><div><strong>{currentUser?.name ?? "Administrator"}</strong><span>{currentUser?.role ?? "user"}</span></div><span className="dots">•••</span></div>
        </div>
      </aside>

      <section className="content">
        <header className="topbar">
          <div>
            <div className="breadcrumb">POOLPILOT <span>/</span> OVERVIEW</div>
            <h1>Pool hall overview</h1>
            <p className="muted">Monitor tables, sessions and verified payments from one place.</p>
          </div>
          <div className="top-actions">
            <button className="icon-button" onClick={loadDashboard} aria-label="Refresh dashboard">↻</button>
            <button className="icon-button notification" aria-label="Notifications">♧</button>
          </div>
        </header>

        <section className="stats-grid">
          <div className="stat-card"><div className="stat-icon blue">▦</div><div><span>Total tables</span><strong>{tables.length}</strong><small>Registered in system</small></div><em>Live</em></div>
          <div className="stat-card"><div className="stat-icon green">✓</div><div><span>Available</span><strong>{available}</strong><small>Ready for customers</small></div><em className="positive">Ready</em></div>
          <div className="stat-card"><div className="stat-icon amber">◷</div><div><span>Active sessions</span><strong>{playing}</strong><small>{pending} awaiting payment</small></div><em className="neutral">Live</em></div>
          <div className="stat-card revenue"><div className="stat-icon purple">KSh</div><div><span>Today's revenue</span><strong>KSh {revenue.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong><small>{todayPayments.length} confirmed payments</small></div><em className="neutral">KES</em></div>
        </section>

        <section className="section-heading" id="tables">
          <div><div className="section-title-row"><h2>Tables</h2><span className="live-dot">Live</span></div><p>Table status refreshes automatically every 10 seconds.</p></div>
          <button className="ghost-button" onClick={loadDashboard}>Refresh <span>↻</span></button>
        </section>

        {error && <div className="alert">{error}</div>}
        {loading && <div className="loading-card"><span className="loader" /> Loading live table data...</div>}

        {!loading && !error && (
          <section className="table-grid">
            {tables.map((table) => (
              <article className="table-card" key={table.id}>
                <div className="table-top"><span className="table-number">{table.table_number.replace('_', ' ')}</span><span className={`status ${table.status}`}>{statusLabel[table.status] ?? table.status.replace('_', ' ')}</span></div>
                <div className="table-visual">
                  <div className="pool-icon"><span /><span /><span /><span /><span /></div>
                  <div className="table-caption"><strong>{table.name}</strong><p>Controller: {table.mqtt_topic}</p></div>
                </div>
                <div className="table-bottom"><div><span>SESSION RATE</span><strong>KSh {table.price}</strong><small>one game / session</small></div><Link className="manage-button" href={`/tables/${table.id}`}>Open <span>→</span></Link></div>
              </article>
            ))}
          </section>
        )}

        {!loading && !error && tables.length === 0 && <div className="loading-card">No tables have been registered yet.</div>}

        <section className="lower-grid" id="payments">
          <div className="panel">
            <div className="panel-heading"><div><h2>Recent payments</h2><p>Verified payment activity from your hall.</p></div><span className="panel-link">{todayPayments.length} today</span></div>
            {recentPayments.length === 0 ? (
              <div className="empty-state"><div className="empty-icon">KSh</div><strong>No recent payments</strong><span>Confirmed M-Pesa transactions will appear here.</span></div>
            ) : (
              <div className="payment-list">
                {recentPayments.map((payment) => (
                  <div className="payment-row" key={payment.id}>
                    <div className="payment-icon">KSh</div>
                    <div className="payment-info"><strong>{payment.table_name ?? payment.table_number ?? `Table #${payment.table_id}`}</strong><span>{payment.transaction_id ?? payment.mpesa_receipt ?? 'Pending reference'} · {payment.status}</span></div>
                    <div className="payment-amount"><strong>KSh {Number(payment.amount).toFixed(2)}</strong><span>{payment.paid_at ?? payment.created_at ?? ''}</span></div>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="panel" id="health">
            <div className="panel-heading"><div><h2>System health</h2><p>Live status of available services.</p></div><span className={`healthy-badge ${apiHealthy ? '' : 'dim'}`}><i /> {apiHealthy ? 'Healthy' : 'Offline'}</span></div>
            <div className="service-row"><span><i className={apiHealthy ? 'ok' : 'waiting'} />PHP API</span><b className={apiHealthy ? '' : 'dim'}>{apiHealthy ? 'Connected' : 'Unavailable'}</b></div>
            <div className="service-row"><span><i className="waiting" />M-Pesa</span><b className="dim">Integration pending</b></div>
            <div className="service-row"><span><i className="waiting" />MQTT</span><b className="dim">Integration pending</b></div>
            <div className="service-row" id="games"><span><i className="ok" />Sessions</span><b>{games.filter((game) => game.status === 'active').length} active</b></div>
          </div>
        </section>

        <footer className="dashboard-footer"><span>PoolPilot Management</span><span>API v0.3.1 · Local development</span></footer>
      </section>
    </main>
  );
}
