'use client';

import { useEffect, useMemo, useState } from 'react';

type PoolTable = {
  id: number;
  table_number: string;
  name: string;
  price: string;
  status: 'available' | 'payment_pending' | 'playing' | 'offline' | string;
  mqtt_topic: string;
};

const navItems = [
  ['Overview', '⌂'],
  ['Tables', '▦'],
  ['Payments', '↗'],
  ['Games', '◷'],
  ['Reports', '▥'],
  ['Settings', '⚙'],
];

const statusLabel: Record<string, string> = {
  available: 'Available',
  playing: 'Playing',
  payment_pending: 'Payment pending',
  offline: 'Offline',
};

export default function Home() {
  const [tables, setTables] = useState<PoolTable[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const apiUrl = process.env.NEXT_PUBLIC_API_URL;
    if (!apiUrl) {
      setError('API URL is not configured.');
      setLoading(false);
      return;
    }

    fetch(`${apiUrl}/tables`)
      .then((response) => {
        if (!response.ok) throw new Error('Failed to fetch tables');
        return response.json();
      })
      .then((result) => setTables(result.data ?? []))
      .catch(() => setError('Unable to connect to the backend.'))
      .finally(() => setLoading(false));
  }, []);

  const available = useMemo(() => tables.filter((table) => table.status === 'available').length, [tables]);
  const playing = useMemo(() => tables.filter((table) => table.status === 'playing').length, [tables]);
  const pending = useMemo(() => tables.filter((table) => table.status === 'payment_pending').length, [tables]);

  return (
    <main className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-mark"><span>8</span></div>
          <div><strong>PoolPilot</strong><span>Operations</span></div>
        </div>
        <div className="nav-label">MAIN MENU</div>
        <nav>
          {navItems.map(([label, icon], index) => (
            <button className={`nav-item ${index === 0 ? 'active' : ''}`} key={label}>
              <span className="nav-icon">{icon}</span><span>{label}</span>
            </button>
          ))}
        </nav>
        <div className="sidebar-card">
          <span className="sidebar-card-kicker">QUICK STATUS</span>
          <strong>{available} tables ready</strong>
          <p>System is operating normally.</p>
          <div className="mini-progress"><span style={{ width: `${tables.length ? (available / tables.length) * 100 : 0}%` }} /></div>
        </div>
        <div className="sidebar-footer">
          <div className="connection"><span className="pulse" /> API connected</div>
          <div className="user-card"><div className="avatar">A</div><div><strong>Administrator</strong><span>Control panel</span></div><span className="dots">•••</span></div>
        </div>
      </aside>

      <section className="content">
        <header className="topbar">
          <div>
            <div className="breadcrumb">POOLPILOT <span>/</span> OVERVIEW</div>
            <h1>Pool hall overview</h1>
            <p className="muted">Monitor tables, sessions and payments from one place.</p>
          </div>
          <div className="top-actions">
            <button className="icon-button" aria-label="Search">⌕</button>
            <button className="icon-button notification" aria-label="Notifications">♧<i /></button>
            <button className="primary-button">+ New session</button>
          </div>
        </header>

        <section className="stats-grid">
          <div className="stat-card"><div className="stat-icon blue">▦</div><div><span>Total tables</span><strong>{tables.length}</strong><small>Registered in system</small></div><em>Live</em></div>
          <div className="stat-card"><div className="stat-icon green">✓</div><div><span>Available</span><strong>{available}</strong><small>Ready for customers</small></div><em className="positive">Ready</em></div>
          <div className="stat-card"><div className="stat-icon amber">◷</div><div><span>Active sessions</span><strong>{playing}</strong><small>{pending} awaiting payment</small></div><em className="neutral">Today</em></div>
          <div className="stat-card revenue"><div className="stat-icon purple">KSh</div><div><span>Today's revenue</span><strong>KSh 0</strong><small>Confirmed payments</small></div><em className="neutral">KES</em></div>
        </section>

        <section className="section-heading">
          <div><div className="section-title-row"><h2>Tables</h2><span className="live-dot">Live</span></div><p>Real-time table availability from the management API.</p></div>
          <button className="ghost-button">View all tables <span>→</span></button>
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
                  <div className="table-caption"><strong>{table.name}</strong><p>Table controller ready</p></div>
                </div>
                <div className="table-bottom"><div><span>SESSION RATE</span><strong>KSh {table.price}</strong><small>per session</small></div><button className="manage-button">Open <span>→</span></button></div>
              </article>
            ))}
          </section>
        )}

        {!loading && !error && tables.length === 0 && <div className="loading-card">No tables have been registered yet.</div>}

        <section className="lower-grid">
          <div className="panel"><div className="panel-heading"><div><h2>Recent payments</h2><p>Payment activity from your hall.</p></div><span className="panel-link">View payments →</span></div><div className="empty-state"><div className="empty-icon">KSh</div><strong>No recent payments</strong><span>Confirmed M-Pesa transactions will appear here.</span></div></div>
          <div className="panel"><div className="panel-heading"><div><h2>System health</h2><p>Connection status of core services.</p></div><span className="healthy-badge"><i /> Healthy</span></div><div className="service-row"><span><i className="ok" />PHP API</span><b>Connected</b></div><div className="service-row"><span><i className="ok" />MySQL database</span><b>Connected</b></div><div className="service-row"><span><i className="waiting" />M-Pesa</span><b className="dim">Not configured</b></div><div className="service-row"><span><i className="waiting" />MQTT</span><b className="dim">Not configured</b></div></div>
        </section>

        <footer className="dashboard-footer"><span>PoolPilot Management</span><span>Backend API v0.2.0 • Local development</span></footer>
      </section>
    </main>
  );
}
