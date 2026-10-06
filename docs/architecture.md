# Architecture

```text
Customer
  │
  │ M-Pesa Pay Bill
  │ Paybill + Table Reference + Amount
  ▼
Safaricom M-Pesa
  │
  │ C2B confirmation/validation
  ▼
PoolPilot PHP API
  ├── MySQL
  │    ├── Organizations / clients
  │    ├── Users / roles
  │    ├── M-Pesa settings
  │    ├── Tables
  │    ├── Payments
  │    ├── Games / sessions
  │    └── MQTT command queue
  │
  └── MQTT / HiveMQ Cloud
             │
             ▼
     External ESP32/controller
             │
             ▼
        Pool table lock

Frontend
  │
  └── Next.js / Vercel
       ├── Admin dashboard
       ├── Table/payment display
       └── Session management
```

## Product model

- The system is session/game based, not time-limit based.
- Payment creates the entitlement to one game.
- A confirmed payment can queue an unlock.
- An attendant ends the game.
- Ending a game records duration for reporting and queues a lock.
- Hardware control remains external to this repository.

## Multi-client model

Each pool business is an organization. Its Paybill, tables, prices, users, payments, games and MQTT command records are scoped to that organization.

The domain is independent from the Paybill. Changing a client's Paybill is a configuration change, not a domain change.

## Security boundary

The backend is the authorization boundary. The frontend cannot mark payments confirmed or issue an unlock command directly. MQTT credentials must remain server-side, and the controller must authenticate with the broker.
