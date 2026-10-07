'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

type User={id:number;name:string;email:string;role:string;active:number};

export default function UsersPage(){
  const router=useRouter();const [me,setMe]=useState<{role:string}|null>(null);const [users,setUsers]=useState<User[]>([]);
  const [name,setName]=useState('');const [email,setEmail]=useState('');const [password,setPassword]=useState('');const [role,setRole]=useState('attendant');
  const [error,setError]=useState('');const [loading,setLoading]=useState(true);const [saving,setSaving]=useState(false);
  const api=process.env.NEXT_PUBLIC_API_URL;
  async function load(){const apiUrl=process.env.NEXT_PUBLIC_API_URL;if(!apiUrl)return;const auth=await fetch(apiUrl+'/auth/me',{credentials:'include'});if(!auth.ok){router.replace('/login');return;}const a=await auth.json();setMe(a.data);if(!['owner','admin'].includes(a.data.role)){router.replace('/');return;}const r=await fetch(apiUrl+'/users',{credentials:'include'});const d=await r.json();if(!r.ok)throw new Error(d.error||'Unable to load users');setUsers(d.data||[]);setLoading(false);}
  useEffect(()=>{load().catch(e=>{setError(e.message);setLoading(false);});},[]);
  async function create(e:FormEvent){e.preventDefault();setError('');setSaving(true);try{const apiUrl=process.env.NEXT_PUBLIC_API_URL;if(!apiUrl){setError('API URL is not configured.');setSaving(false);return;}const r=await fetch(apiUrl+'/users',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({name,email,password,role})});const d=await r.json();if(!r.ok)throw new Error(d.error||'Unable to create user');setName('');setEmail('');setPassword('');setRole('attendant');await load();}catch(e){setError(e instanceof Error?e.message:'Unable to create user');}finally{setSaving(false);}}
  async function toggle(u:User){const r=await fetch(apiUrl+'/users/'+u.id,{method:'PUT',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({active:!u.active})});const d=await r.json();if(!r.ok){setError(d.error||'Unable to update user');return;}await load();}
  return <main className="management-page"><header className="management-header"><div><Link href="/">← Dashboard</Link><span>TEAM MANAGEMENT</span><h1>Users</h1><p>Create and manage staff access to PoolPilot.</p></div><div className="role-badge">{me?.role}</div></header>
    <div className="users-layout"><section className="detail-card"><div className="detail-card-heading"><div><span className="detail-eyebrow">CREATE ACCOUNT</span><h2>Add team member</h2></div></div>
      <form onSubmit={create} className="user-form"><label>Name<input value={name} onChange={e=>setName(e.target.value)} required /></label><label>Email<input type="email" value={email} onChange={e=>setEmail(e.target.value)} required /></label><label>Temporary password<input type="password" minLength={8} value={password} onChange={e=>setPassword(e.target.value)} required /></label><label>Role<select value={role} onChange={e=>setRole(e.target.value)}><option value="attendant">Attendant</option><option value="accountant">Accountant</option><option value="admin">Administrator</option>{me?.role==='owner'&&<option value="owner">Owner</option>}</select></label>{error&&<div className="auth-error">{error}</div>}<button className="auth-submit" disabled={saving}>{saving?'Creating…':'Create account'}</button></form>
    </section><section className="detail-card"><div className="detail-card-heading"><div><span className="detail-eyebrow">ACCESS CONTROL</span><h2>Team members</h2></div><span className="count-pill">{users.length}</span></div>{loading?<div className="detail-empty">Loading users…</div>:<div className="users-list">{users.map(u=><div className="user-row" key={u.id}><div className="avatar">{u.name.charAt(0).toUpperCase()}</div><div><strong>{u.name}</strong><span>{u.email}</span></div><b>{u.role}</b><button onClick={()=>toggle(u)}>{u.active?'Deactivate':'Activate'}</button></div>)}</div>}</section></div>
  </main>;
}
