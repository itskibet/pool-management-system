const tables = [
  { code: 'TABLE01', name: 'Table 01', price: 30 },
  { code: 'TABLE02', name: 'Table 02', price: 30 },
  { code: 'TABLE03', name: 'Table 03', price: 30 },
  { code: 'TABLE04', name: 'Table 04', price: 30 },
  { code: 'TABLE05', name: 'Table 05', price: 30 },
];

export default function Home() {
  return (
    <main className="shell">
      <div className="container">
        <header className="header">
          <div className="brand">Pool Management System</div>
          <div className="badge">Payment verification enabled</div>
        </header>

        <section className="hero">
          <h1>Choose your table</h1>
          <p>
            Pay with a smartphone QR flow or use the normal M-Pesa SIM Toolkit/USSD flow.
            A smartphone is not required.
          </p>
        </section>

        <section className="grid" aria-label="Pool tables">
          {tables.map((table) => (
            <article className="card" key={table.code}>
              <h2>{table.name}</h2>
              <div className="price">KSh {table.price}</div>
              <p className="muted">Choose QR payment or manual M-Pesa.</p>
              <div className="actions">
                <button className="button" type="button">Pay by QR</button>
                <button className="button secondary" type="button">Use M-Pesa manually</button>
              </div>
            </article>
          ))}
        </section>

        <footer className="footer">
          Payments are confirmed by the backend before a table can be unlocked.
        </footer>
      </div>
    </main>
  );
}
