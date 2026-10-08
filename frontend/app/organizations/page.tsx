'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

type Organization={id:number;name:string;slug:string;paybill_number?:string;account_prefix?:string;user_count:number;table_count:number};

export default function OrganizationsPage(){
  const router=useRouter(); const api=process.env.NEXT_PUBLIC_API_URL;
  const [orgs,setOrgs]=useState<Organization[]>([]); const [error,setError]=useState(''); const [saving,setSaving]=useState(false);
  const [name,setName]=useState(''); const [slug,setSlug]=useState(''); const [paybill,setPaybill]=useState('');
  const [ownerName,setOwnerName]=useState(''); const [ownerEmail,setOwnerEmail]=useState(''); const [ownerPassword,setOwnerPassword]=useState('');
  async function load(){if(!api)return;const me=await fetch(api+'/auth/me',{credentials:'include'});if(!me.ok){router.replace('/login');return;}const m=await me.json();if(!m.data?.is_system_admin){router.replace('/');return;}const r=await fetch(api+'/organizations',{credentials:'include'});const d=await r.json();if(!r.ok)throw new Error(d.error||'Unable to load clients');setOrgs(d.data||[]);}
  useEffect(()=>{load().catch(e=>setError(e.message));},[]);
  async function create(e:FormEvent){e.preventDefault();setError('');setSaving(true);try{const r=await fetch(api+'/organizations',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({name,slug,paybill_number:paybill,account_prefix:'TABLE',owner_name:ownerName,owner_email:ownerEmail,owner_password:ownerPassword})});const d=await r.json();if(!r.ok)throw new Error(d.error||'Unable to create client');setName('');setSlug('');setPaybill('');setOwnerName('');setOwnerEmail('');setOwnerPassword('');await load();}catch(e){setError(e instanceof Error?e.message:'Unable to create client');}finally{setSaving(false);}}
  return <main className="management-page"><header className="management-header"><div><Link href="/">← Dashboard</Link><span>PLATFORM ADMINISTRATION</span><h1>Clients</h1><p>Create separate pool businesses with their own Paybill and owner account.</p></div><div className="role-badge">System admin</div></header>
  <div className="users-layout"><section className="detail-card"><div className="detail-card-heading"><div><span className="detail-eyebrow">NEW CLIENT</span><h2>Add client</h2></div></div>
  <form onSubmit={create} className="user-form"><label>Business name<input value={name} onChange={e=>setName(e.target.value)} required /></label><label>Slug<input value={slug} onChange={e=>setSlug(e.target.value)} placeholder="example-pool-hall" required /></label><label>Paybill number<input value={paybill} onChange={e=>setPaybill(e.target.value)} required /></label><label>Client owner name<input value={ownerName} onChange={e=>setOwnerName(e.target.value)} required /></label><label>Client owner email<input type="email" value={ownerEmail} onChange={e=>setOwnerEmail(e.target.value)} required /></label><label>Temporary password<input type="password" minLength={8} value={ownerPassword} onChange={e=>setOwnerPassword(e.target.value)} required /></label>{error&&<div className="auth-error">{error}</div>}<button className="auth-submit" disabled={saving}>{saving?'Creating…':'Create client'}</button></form></section>
  <section className="detail-card"><div className="detail-card-heading"><div><span className="detail-eyebrow">CLIENTS</span><h2>Pool businesses</h2></div><span className="count-pill">{orgs.length}</span></div><div className="users-list">{orgs.map(o=><div className="user-row" key={o.id}><div className="avatar">{o.name.charAt(0).toUpperCase()}</div><div><strong>{o.name}</strong><span>Paybill: {o.paybill_number||'Not configured'} · {o.user_count} users · {o.table_count} tables</span></div><b>{o.slug}</b></div>)}</div></section></div></main>;
}
