'use client';

import Link from 'next/link';

function PoolMascot() {
  return (
    <svg className="welcome-mascot" viewBox="0 0 420 390" role="img" aria-label="Friendly PoolPilot eight-ball mascot beside a pool table">
      <defs>
        <linearGradient id="eightball" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#43536a"/><stop offset="1" stopColor="#091321"/></linearGradient>
        <linearGradient id="felt" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#35c38b"/><stop offset="1" stopColor="#08734e"/></linearGradient>
      </defs>
      <ellipse cx="210" cy="343" rx="142" ry="16" fill="#0b1d2b" opacity=".1"/>
      <g transform="translate(26 235) rotate(-5 175 45)">
        <rect x="8" y="17" width="342" height="91" rx="16" fill="#173d36"/>
        <rect x="19" y="5" width="320" height="79" rx="11" fill="#ad7544"/>
        <rect x="29" y="13" width="300" height="63" rx="7" fill="url(#felt)"/>
        <g fill="#092c28"><circle cx="37" cy="21" r="6"/><circle cx="179" cy="17" r="6"/><circle cx="321" cy="21" r="6"/><circle cx="37" cy="68" r="6"/><circle cx="179" cy="72" r="6"/><circle cx="321" cy="68" r="6"/></g>
        <g><circle cx="231" cy="36" r="9" fill="#fff"/><circle cx="262" cy="56" r="9" fill="#f2b544"/><circle cx="291" cy="34" r="9" fill="#ed6b67"/></g>
        <path d="M62 85v17 M295 85v17" stroke="#80502f" strokeWidth="10" strokeLinecap="round"/>
      </g>
      <g transform="translate(110 26)">
        <circle cx="105" cy="133" r="99" fill="url(#eightball)"/>
        <ellipse cx="76" cy="65" rx="35" ry="17" fill="#fff" opacity=".08" transform="rotate(-25 76 65)"/>
        <circle cx="105" cy="133" r="51" fill="#f7f8fa"/>
        <text x="105" y="156" textAnchor="middle" fontSize="69" fontWeight="800" fontFamily="Arial,sans-serif" fill="#111c2b">8</text>
        <ellipse cx="73" cy="128" rx="7" ry="10" fill="#111c2b"/><ellipse cx="137" cy="128" rx="7" ry="10" fill="#111c2b"/>
        <circle cx="75" cy="124" r="3" fill="#fff"/><circle cx="139" cy="124" r="3" fill="#fff"/>
        <path d="M84 151 Q105 170 126 151" fill="none" stroke="#111c2b" strokeWidth="5" strokeLinecap="round"/>
        <path d="M35 216 Q12 231 22 250 Q32 260 49 241 M175 216 Q199 229 190 247 Q181 259 164 240" fill="#1d2d43" stroke="#0c1727" strokeWidth="5"/>
        <path d="M75 227 L70 254 Q105 275 140 254 L135 227" fill="#1d2d43"/>
      </g>
      <g transform="translate(310 66)"><circle cx="25" cy="25" r="24" fill="#e2f8ed"/><path d="M15 25l7 7 14-16" fill="none" stroke="#159765" strokeWidth="5" strokeLinecap="round" strokeLinejoin="round"/></g>
      <path d="M60 137l6 12 12 6-12 6-6 12-6-12-12-6 12-6z" fill="#f3bd58"/>
      <circle cx="328" cy="201" r="4" fill="#24a879"/><circle cx="83" cy="192" r="3" fill="#24a879"/>
    </svg>
  );
}

export default function WelcomePage() {
  return (
    <main className="welcome-page">
      <nav className="welcome-nav">
        <Link className="welcome-brand" href="/" aria-label="PoolPilot home">
          <span className="welcome-brand-mark">8</span>
          <span><strong>PoolPilot</strong><small>POOL HALL MANAGEMENT</small></span>
        </Link>
        <Link className="welcome-nav-login" href="/login">Log in <span aria-hidden="true">↗</span></Link>
      </nav>
      <section className="welcome-hero">
        <div className="welcome-copy">
          <div className="welcome-eyebrow"><span /> YOUR HALL. UNDER CONTROL.</div>
          <h1>More play.<br/><span>Less hassle.</span></h1>
          <p className="welcome-description">Run your pool hall with confidence. Keep tables organised, track sessions and bring your daily operations together in one simple place.</p>
          <div className="welcome-actions">
            <Link className="welcome-primary" href="/login">Login to PoolPilot <span aria-hidden="true">→</span></Link>
            <span className="welcome-secure"><span aria-hidden="true">✓</span> Secure access for your team</span>
          </div>
          <div className="welcome-highlights">
            <div><span className="welcome-highlight-icon">▦</span><span><strong>Table control</strong><small>Know what’s available</small></span></div>
            <div><span className="welcome-highlight-icon">↗</span><span><strong>Payment tracking</strong><small>Follow each session</small></span></div>
          </div>
        </div>
        <div className="welcome-art-wrap">
          <div className="welcome-art-glow" />
          <div className="welcome-art-badge"><span className="welcome-status-dot" /> READY WHEN YOU ARE</div>
          <PoolMascot />
          <div className="welcome-art-caption"><span className="welcome-caption-icon">8</span><span><strong>Meet your table-side teammate.</strong><small>Welcome to a smoother way to manage your hall.</small></span></div>
        </div>
      </section>
      <footer className="welcome-footer"><span>© {new Date().getFullYear()} PoolPilot</span><span>Built for better-run pool halls</span></footer>
    </main>
  );
}
