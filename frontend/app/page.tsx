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
          <div className="brand-mark">P</div>
          <div><strong>PoolPilot</strong><span>Management</span></div>
        </div>
        <div className="nav-label">WORKSPACE</div>
        <nav>
          {navItems.map(([label, icon], index) => (
            <button className={`nav-item ${index === 0 ? 'active' : ''}`} key={label}>
              <span className="nav-icon">{icon}</span>{label}
            </button>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="connection"><span className="pulse" /> System online</div>
          <div className="user-card"><div className="avatar">A</div><div><strong>Administrator</strong><span>Control panel</span></div><span className="dots">•••</span></div>
        </div>
      </aside>

      <section className="content">
        <header className="topbar">
          <div><p className="eyebrow">OPERATIONS</p><h1>Good evening, Admin</h1><p className="muted">Here is what is happening across your pool hall today.</p></div>
          <div className="top-actions"><button className="icon-button">⌕</button><button className="icon-button notification">♧<i /></button><button className="primary-button">+ New session</button></div>
        </header>

        <section className="stats-grid">
          <div className="stat-card"><div className="stat-icon blue">▦</div><div><span>Total tables</span><strong>{tables.length}</strong><small>All registered tables</small></div></div>
          <div className="stat-card"><div className="stat-icon green">✓</div><div><span>Available now</span><strong>{available}</strong><small>Ready for customers</small></div></div>
          <div className="stat-card"><div className="stat-icon amber">◷</div><div><span>Active games</span><strong>{playing}</strong><small>{pending} payment pending</small></div></div>
          <div className="stat-card revenue"><div className="stat-icon purple">KSh</div><div><span>Today's revenue</span><strong>KSh 0</strong><small>Confirmed payments</small></div></div>
        </section>

        <section className="section-heading"><div><h2>Table overview</h2><p>Live status from your pool tables.</p></div><button className="ghost-button">View all tables <span>→</span></button></section>

        {error && <div className="alert">{error}</div>}
        {loading && <div className="loading-card">Loading live table data...</div>}

        {!loading && !error && (
          <section className="table-grid">
            {tables.map((table) => (
              <article className="table-card" key={table.id}>
                <div className="table-top"><span className="table-number">{table.table_number.replace('_', ' ')}</span><span className={`status ${table.status}`}>{table.status.replace('_', ' ')}</span></div>
                <div className="table-visual"><div className="pool-icon"><span /><span /><span /></div><div><strong>{table.name}</strong><p>MQTT ready</p></div></div>
                <div className="table-bottom"><div><span>Session rate</span><strong>KSh {table.price}</strong></div><button className="manage-button">Manage</button></div>
              </article>
            ))}
          </section>
        )}

        {!loading && !error && tables.length === 0 && <div className="loading-card">No tables have been registered yet.</div>}

        <section className="lower-grid">
          <div className="panel"><div className="panel-heading"><div><h2>Recent payments</h2><p>Latest transactions will appear here.</p></div><span className="panel-link">Payments →</span></div><div className="empty-state"><div className="empty-icon">₿</div><strong>No recent payments</strong><span>Confirmed M-Pesa transactions will be shown here.</span></div></div>
          <div className="panel"><div className="panel-heading"><div><h2>System status</h2><p>Core services and connectivity.</p></div></div><div className="service-row"><span><i className="ok" />PHP API</span><b>Connected</b></div><div className="service-row"><span><i className="ok" />MySQL database</span><b>Connected</b></div><div className="service-row"><span><i className="waiting" />M-Pesa</span><b className="dim">Not configured</b></div><div className="service-row"><span><i className="waiting" />MQTT</span><b className="dim">Not configured</b></div></div>
        </section>
      </section>
    </main>
  );
}
