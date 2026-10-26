'use client';

import { FormEvent, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';

type Account = {
  id: number;
  name: string;
  email: string;
  role: string;
  is_system_admin: number | boolean;
};

export default function AccountPage() {
  const router = useRouter();
  const api = process.env.NEXT_PUBLIC_API_URL;
  const [account, setAccount] = useState<Account | null>(null);
  const [loading, setLoading] = useState(true);
  const [profileSaving, setProfileSaving] = useState(false);
  const [passwordSaving, setPasswordSaving] = useState(false);
  const [profileError, setProfileError] = useState('');
  const [profileSuccess, setProfileSuccess] = useState('');
  const [passwordError, setPasswordError] = useState('');
  const [passwordSuccess, setPasswordSuccess] = useState('');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [profileCurrentPassword, setProfileCurrentPassword] = useState('');
  const [passwordCurrentPassword, setPasswordCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  useEffect(() => {
    let cancelled = false;
    async function loadAccount() {
      if (!api) {
        setProfileError('API URL is not configured.');
        setLoading(false);
        return;
      }
      try {
        const response = await fetch(api + '/auth/me', { credentials: 'include', cache: 'no-store' });
        if (!response.ok) {
          router.replace('/login');
          return;
        }
        const result = await response.json();
        if (cancelled) return;
        setAccount(result.data);
        setName(result.data.name ?? '');
        setEmail(result.data.email ?? '');
      } catch {
        if (!cancelled) setProfileError('Unable to load account details. Check the backend connection.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    loadAccount();
    return () => { cancelled = true; };
  }, [api, router]);

  async function updateProfile(event: FormEvent) {
    event.preventDefault();
    setProfileError('');
    setProfileSuccess('');
    setProfileSaving(true);
    try {
      if (!api) throw new Error('API URL is not configured.');
      const response = await fetch(api + '/auth/profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          name: name.trim(),
          email: email.trim(),
          current_password: profileCurrentPassword,
        }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to update account details.');
      setAccount(result.data);
      setName(result.data.name);
      setEmail(result.data.email);
      setProfileCurrentPassword('');
      setProfileSuccess('Your account details have been updated. Use your new login email the next time you sign in.');
    } catch (error) {
      setProfileError(error instanceof Error ? error.message : 'Unable to update account details.');
    } finally {
      setProfileSaving(false);
    }
  }

  async function updatePassword(event: FormEvent) {
    event.preventDefault();
    setPasswordError('');
    setPasswordSuccess('');
    if (newPassword !== confirmPassword) {
      setPasswordError('The new password and confirmation do not match.');
      return;
    }
    setPasswordSaving(true);
    try {
      if (!api) throw new Error('API URL is not configured.');
      const response = await fetch(api + '/auth/profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          current_password: passwordCurrentPassword,
          new_password: newPassword,
          confirm_new_password: confirmPassword,
        }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to change password.');
      setPasswordCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
      setPasswordSuccess('Password changed successfully. Keep it private and use it next time you sign in.');
    } catch (error) {
      setPasswordError(error instanceof Error ? error.message : 'Unable to change password.');
    } finally {
      setPasswordSaving(false);
    }
  }

  return (
    <main className="management-page">
      <header className="management-header">
        <div>
          <Link href="/dashboard">← Dashboard</Link>
          <span>PERSONAL SETTINGS</span>
          <h1>My account</h1>
          <p>Update your login details and keep your account secure.</p>
        </div>
        {account && <div className="role-badge">{account.is_system_admin ? 'System administrator' : account.role}</div>}
      </header>

      {loading ? <section className="detail-card detail-loading">Loading account details…</section> : (
        <div className="users-layout account-layout">
          <section className="detail-card">
            <div className="detail-card-heading">
              <div><span className="detail-eyebrow">PROFILE</span><h2>Personal details</h2></div>
            </div>
            <p className="account-help">Your login email is your username in PoolPilot. Changing it will not change your role or organization.</p>
            <form className="user-form" onSubmit={updateProfile}>
              <label>Display name<input value={name} onChange={event => setName(event.target.value)} maxLength={120} required autoComplete="name" /></label>
              <label>Login email<input type="email" value={email} onChange={event => setEmail(event.target.value)} maxLength={190} required autoComplete="username" /></label>
              <label>Current password<input type="password" value={profileCurrentPassword} onChange={event => setProfileCurrentPassword(event.target.value)} required autoComplete="current-password" /></label>
              {profileError && <div className="auth-error" role="alert">{profileError}</div>}
              {profileSuccess && <div className="account-success" role="status">{profileSuccess}</div>}
              <button className="auth-submit" disabled={profileSaving || loading}>{profileSaving ? 'Saving…' : 'Save profile'}</button>
            </form>
          </section>

          <section className="detail-card">
            <div className="detail-card-heading">
              <div><span className="detail-eyebrow">SECURITY</span><h2>Change password</h2></div>
            </div>
            <p className="account-help">Use at least 12 characters. A longer, unique passphrase is recommended.</p>
            <form className="user-form" onSubmit={updatePassword}>
              <label>Current password<input type="password" value={passwordCurrentPassword} onChange={event => setPasswordCurrentPassword(event.target.value)} required autoComplete="current-password" /></label>
              <label>New password<input type="password" value={newPassword} onChange={event => setNewPassword(event.target.value)} minLength={12} maxLength={200} required autoComplete="new-password" /></label>
              <label>Confirm new password<input type="password" value={confirmPassword} onChange={event => setConfirmPassword(event.target.value)} minLength={12} maxLength={200} required autoComplete="new-password" /></label>
              {passwordError && <div className="auth-error" role="alert">{passwordError}</div>}
              {passwordSuccess && <div className="account-success" role="status">{passwordSuccess}</div>}
              <button className="auth-submit" disabled={passwordSaving || loading}>{passwordSaving ? 'Updating…' : 'Change password'}</button>
            </form>
            <div className="security-note"><span>✓</span><div><strong>Your role stays protected</strong><small>You can change your own credentials, but not grant yourself extra permissions or access another organization.</small></div></div>
          </section>
        </div>
      )}
    </main>
  );
}
