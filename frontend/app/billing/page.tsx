'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

type Closing={id:number;business_date:string;gross_revenue:string;platform_fee_rate:string;platform_fee_amount:string;status:string;closed_at:string;closed_by_name?:string};

export default function BillingPage(){
 const router=useRouter();const api=process.env.NEXT_PUBLIC_API_URL;const [closings,setClosings]=useState<Closing[]>([]);const [date,setDate]=useState(new Date().toISOString().slice(0,10));const [error,setError]=useState('');const [saving,setSaving]=useState(false);
 async function load(){if(!api)return;const me=await fetch(api+'/auth/me',{credentials:'include'});if(!me.ok){router.replace('/login');return;}const m=await me.json();if(!['owner','admin'].includes(m.data?.role)){router.replace('/');return;}const r=await fetch(api+'/billing/daily-closings',{credentials:'include'});const d=await r.json();if(!r.ok)throw new Error(d.error||'Unable to load daily closings');setClosings(d.data||[]);}
 useEffect(()=>{load().catch(e=>setError(e.message));},[]);
 async function closeDay(e:FormEvent){e.preventDefault();setError('');setSaving(true);try{const r=await fetch(api+'/billing/close-day',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({business_date:date})});const d=await r.json();if(!r.ok)throw new Error(d.error||'Unable to close day');await load();}catch(e){setError(e instanceof Error?e.message:'Unable to close day');}finally{setSaving(false);}}
 return <main className="management-page"><header className="management-header"><div><Link href="/">← Dashboard</Link><span>FINANCE</span><h1>Daily closing</h1><p>Close a business day and calculate the 4% platform fee from confirmed revenue.</p></div><div className="role-badge">4% platform fee</div></header>
 <div className="users-layout"><section className="detail-card"><div className="detail-card-heading"><div><span className="detail-eyebrow">CLOSE DAY</span><h2>Daily settlement</h2></div></div><form onSubmit={closeDay} className="user-form"><label>Business date<input type="date" value={date} onChange={e=>setDate(e.target.value)} required /></label>{error&&<div className="auth-error">{error}</div>}<button className="auth-submit" disabled={saving}>{saving?'Closing…':'Close business day'}</button></form></section>
 <section className="detail-card"><div className="detail-card-heading"><div><span className="detail-eyebrow">HISTORY</span><h2>Closed days</h2></div><span className="count-pill">{closings.length}</span></div><div className="users-list">{closings.map(c=><div className="user-row" key={c.id}><div className="avatar">KSh</div><div><strong>{c.business_date}</strong><span>Gross: KSh {Number(c.gross_revenue).toFixed(2)} · Fee: KSh {Number(c.platform_fee_amount).toFixed(2)}</span></div><b>{c.platform_fee_rate}%</b></div>)}</div></section></div></main>;
}
