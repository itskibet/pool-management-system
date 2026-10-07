'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

export default function LoginPage() {
  const router=useRouter();
  const [email,setEmail]=useState('');
  const [password,setPassword]=useState('');
  const [error,setError]=useState('');
  const [loading,setLoading]=useState(false);

  useEffect(()=>{(async()=>{const api=process.env.NEXT_PUBLIC_API_URL;if(!api)return;const r=await fetch(api+'/auth/me',{credentials:'include'});if(r.ok)router.replace('/');})();},[router]);

  async function submit(e:FormEvent){e.preventDefault();setError('');setLoading(true);
    try{const api=process.env.NEXT_PUBLIC_API_URL;if(!api)throw new Error('API URL is not configured.');
      const r=await fetch(api+'/auth/login',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({email,password})});
      const d=await r.json();if(!r.ok)throw new Error(d.error||'Unable to sign in');router.replace('/');
    }catch(err){setError(err instanceof Error?err.message:'Unable to sign in');}finally{setLoading(false);}
  }

  return <main className="auth-page"><section className="auth-card">
    <div className="auth-brand"><div className="brand-mark"><span>8</span></div><div><strong>PoolPilot</strong><span>Operations</span></div></div>
    <div className="auth-heading"><span>SECURE ACCESS</span><h1>Welcome back</h1><p>Sign in to manage your pool hall.</p></div>
    <form onSubmit={submit} className="auth-form">
      <label>Email<input type="email" value={email} onChange={e=>setEmail(e.target.value)} placeholder="admin@example.com" autoComplete="username" required /></label>
      <label>Password<input type="password" value={password} onChange={e=>setPassword(e.target.value)} placeholder="Enter your password" autoComplete="current-password" required /></label>
      {error&&<div className="auth-error">{error}</div>}
      <button className="auth-submit" disabled={loading}>{loading?'Signing in…':'Sign in'}</button>
    </form>
    <p className="auth-footer">First-time setup? <Link href="/setup">Create the owner account</Link></p>
  </section></main>;
}
