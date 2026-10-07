'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

export default function SetupPage(){
  const router=useRouter(); const [required,setRequired]=useState<boolean|null>(null);
  const [name,setName]=useState('');const [email,setEmail]=useState('');const [password,setPassword]=useState('');const [key,setKey]=useState('');
  const [error,setError]=useState('');const [loading,setLoading]=useState(false);
  useEffect(()=>{(async()=>{const api=process.env.NEXT_PUBLIC_API_URL;if(!api)return;const r=await fetch(api+'/auth/setup-status');const d=await r.json();if(!d.data.setup_required)router.replace('/login');else setRequired(true);})();},[router]);
  async function submit(e:FormEvent){e.preventDefault();setError('');setLoading(true);try{const api=process.env.NEXT_PUBLIC_API_URL;if(!api)throw new Error('API URL is not configured.');
    const r=await fetch(api+'/auth/bootstrap',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,email,password,setup_key:key})});const d=await r.json();if(!r.ok)throw new Error(d.error||'Setup failed');router.replace('/login');
  }catch(err){setError(err instanceof Error?err.message:'Setup failed');}finally{setLoading(false);}}
  if(required===null)return <main className="auth-page"><div className="auth-loading">Checking setup…</div></main>;
  return <main className="auth-page"><section className="auth-card">
    <div className="auth-brand"><div className="brand-mark"><span>8</span></div><div><strong>PoolPilot</strong><span>Initial setup</span></div></div>
    <div className="auth-heading"><span>FIRST-TIME SETUP</span><h1>Create owner account</h1><p>This creates the first account with full system ownership.</p></div>
    <form onSubmit={submit} className="auth-form">
      <label>Full name<input value={name} onChange={e=>setName(e.target.value)} placeholder="Your name" required /></label>
      <label>Email<input type="email" value={email} onChange={e=>setEmail(e.target.value)} placeholder="owner@example.com" required /></label>
      <label>Password<input type="password" value={password} onChange={e=>setPassword(e.target.value)} placeholder="At least 8 characters" minLength={8} required /></label>
      <label>Setup key<input type="password" value={key} onChange={e=>setKey(e.target.value)} placeholder="Bootstrap key" required /></label>
      {error&&<div className="auth-error">{error}</div>}
      <button className="auth-submit" disabled={loading}>{loading?'Creating…':'Create owner account'}</button>
    </form>
    <p className="auth-footer">Already configured? <Link href="/login">Return to sign in</Link></p>
  </section></main>;
}
