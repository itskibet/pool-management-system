'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';

type Game = {
  id: number;
  table_id: number;
  table_number?: string;
  table_name?: string;
  payment_id?: number | null;
  started_at?: string | null;
  ended_at?: string | null;
  duration_seconds?: number | null;
  status: string;
  created_at?: string | null;
};

const dateLabel = (value?: string | null) => {
  if (!value) return '—';
  const date = new Date(value.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('en-KE');
};

const durationLabel = (seconds?: number | null) => {
  if (seconds == null || !Number.isFinite(Number(seconds))) return 'In progress';
  const total = Math.max(0, Number(seconds));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const remaining = total % 60;
  return hours ? `${hours}h ${minutes}m` : minutes ? `${minutes}m ${remaining}s` : `${remaining}s`;
};

export default function GamesPage() {
  const router = useRouter();
  const [games, setGames] = useState<Game[]>([]);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [endingId, setEndingId] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const load = useCallback(async () => {
    const api = process.env.NEXT_PUBLIC_API_URL;
    if (!api) { setError('API URL is not configured.'); setLoading(false); return; }
    try {
      const response = await fetch(`${api}/games`, { credentials: 'include', cache: 'no-store' });
      if (response.status === 401) { router.replace('/login'); return; }
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to load games.');
      setGames(result.data ?? []);
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
        await response.json();
        await load();
      })
      .catch(() => { setError('Unable to verify your session.'); setLoading(false); });
  }, [load, router]);

  const filtered = useMemo(() => games.filter(game => {
    const matchesStatus = status === 'all' || game.status.toLowerCase() === status;
    const haystack = [game.id, game.table_number, game.table_name, game.payment_id, game.status]
      .filter(Boolean).join(' ').toLowerCase();
    return matchesStatus && haystack.includes(search.toLowerCase().trim());
  }), [games, search, status]);

  async function endGame(game: Game) {
    if (game.status !== 'active') return;
    if (!window.confirm(`End the session on ${game.table_name ?? game.table_number ?? `Table ${game.table_id}`}? This queues a table-lock command.`)) return;
    const api = process.env.NEXT_PUBLIC_API_URL;
    if (!api) return;
    setEndingId(game.id); setError(''); setNotice('');
    try {
      const response = await fetch(`${api}/games/${game.id}/end`, {
        method: 'POST', credentials: 'include', headers: { Accept: 'application/json' },
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to end this game.');
      setNotice(result.message || `Game #${game.id} ended. The table-lock command was queued.`);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unable to end this game.');
    } finally { setEndingId(null); }
  }

  return (
    <main className="management-page">
      <header className="management-header">
        <div>
          <Link href="/dashboard">← Back to overview</Link>
          <span>POOLPILOT / GAMES</span>
          <h1>Games & sessions</h1>
          <p>Monitor active play and completed sessions. Ending a session queues a lock command.</p>
        </div>
        <div className="role-badge">Live session register</div>
      </header>

      <section className="stats-grid" style={{ maxWidth: 1280, margin: '0 auto 20px' }}>
        <div className="stat-card"><div className="stat-icon blue">◷</div><div><span>All sessions</span><strong>{games.length}</strong><small>Recorded games</small></div></div>
        <div className="stat-card"><div className="stat-icon green">●</div><div><span>Active</span><strong>{games.filter(g => g.status === 'active').length}</strong><small>Currently in play</small></div></div>
        <div className="stat-card"><div className="stat-icon purple">✓</div><div><span>Completed</span><strong>{games.filter(g => g.status === 'completed').length}</strong><small>Sessions ended</small></div></div>
        <div className="stat-card"><div className="stat-icon amber">↻</div><div><span>Displayed</span><strong>{filtered.length}</strong><small>Matches current filters</small></div></div>
      </section>

      <section className="detail-card" style={{ maxWidth: 1280, margin: '0 auto' }}>
        <div className="detail-card-heading">
          <div><span className="detail-eyebrow">SESSION REGISTER</span><h2>Game history</h2></div>
          <button className="ghost-button" onClick={() => { setLoading(true); void load(); }}>Refresh ↻</button>
        </div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, marginTop: 18 }}>
          <input aria-label="Search games" value={search} onChange={e => setSearch(e.target.value)}
            placeholder="Search table or game ID…" style={{ flex: '1 1 240px', minWidth: 0, border: '1px solid #dfe5ed', borderRadius: 10, padding: '11px 12px', font: 'inherit', fontSize: 11 }} />
          <select aria-label="Filter game status" value={status} onChange={e => setStatus(e.target.value)}
            style={{ border: '1px solid #dfe5ed', borderRadius: 10, padding: '11px 12px', background: '#fff', font: 'inherit', fontSize: 11 }}>
            <option value="all">All sessions</option><option value="active">Active</option><option value="completed">Completed</option>
          </select>
        </div>
        {error && <div className="alert" style={{ marginTop: 16 }}>{error}</div>}
        {notice && <div style={{ marginTop: 16, padding: '12px 14px', borderRadius: 10, background: '#eaf8f2', color: '#238c63', fontSize: 10 }}>{notice}</div>}
        {loading ? <div className="loading-card" style={{ marginTop: 16 }}>Loading games…</div> : (
          <div style={{ overflowX: 'auto', marginTop: 16 }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 760, fontSize: 11 }}>
              <thead><tr style={{ textAlign: 'left', color: '#8490a0', fontSize: 9 }}>
                {['Game', 'Table', 'Payment', 'Started', 'Ended / duration', 'Status', 'Action'].map(label => <th key={label} style={{ padding: '12px 10px', borderBottom: '1px solid #e8ecf1' }}>{label}</th>)}
              </tr></thead>
              <tbody>
                {filtered.map(game => (
                  <tr key={game.id}>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}><strong>#{game.id}</strong></td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}><strong>{game.table_name ?? game.table_number ?? `Table ${game.table_id}`}</strong><div style={{ color: '#8b95a2', fontSize: 9, marginTop: 4 }}>Table ID: {game.table_id}</div></td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}>{game.payment_id ? `#${game.payment_id}` : '—'}</td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4', color: '#7d889a', fontSize: 9 }}>{dateLabel(game.started_at)}</td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4', color: '#7d889a', fontSize: 9 }}>{dateLabel(game.ended_at)}<div style={{ marginTop: 4 }}>{durationLabel(game.duration_seconds)}</div></td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}><span className={`status ${game.status === 'active' ? 'playing' : 'available'}`}>{game.status}</span></td>
                    <td style={{ padding: '14px 10px', borderBottom: '1px solid #eef1f4' }}>{game.status === 'active' ? <button className="ghost-button" disabled={endingId === game.id} onClick={() => void endGame(game)}>{endingId === game.id ? 'Ending…' : 'End session'}</button> : <span style={{ color: '#a0a8b5', fontSize: 9 }}>Complete</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {!filtered.length && <div className="detail-empty"><strong>No matching sessions</strong><span>Try a different search or status filter.</span></div>}
          </div>
        )}
        <p style={{ color: '#8b95a2', fontSize: 9, lineHeight: 1.6, marginTop: 18 }}>Ending a game changes its status, makes its table available, and queues a lock command through the existing backend route. Use this action only when the customer has finished playing.</p>
      </section>
    </main>
  );
}
