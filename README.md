# Pool Management System

Production-oriented software platform for paid pool-table sessions, payment verification, table control, and operational reporting.

## Technology

- **Frontend:** Next.js + TypeScript, deployable to Vercel
- **Backend:** Plain PHP REST API
- **Database:** MySQL
- **Payments:** Safaricom M-Pesa / Daraja
- **Messaging:** MQTT / HiveMQ Cloud
- **Hardware:** External ESP32/controller connected to the existing pool hardware
- **CI:** GitHub Actions

## Payment model

The system deliberately supports customers with and without smartphones:

1. **QR / smartphone payment** — customer scans a table QR and completes M-Pesa payment.
2. **Manual M-Pesa / SIM Toolkit** — customer uses the normal M-Pesa menu/USSD flow with the client's Paybill and a unique table/session reference.
3. **Assisted payment** — authenticated staff can record an assisted transaction with audit logging.

QR is optional. A customer must not need a smartphone to use the system.

## Anti-fraud principle

The frontend never decides that money has been paid. A table is unlocked only after the backend independently verifies an authoritative M-Pesa transaction, confirms the expected amount and table/session reference, prevents duplicate receipts, creates the game session, and publishes the MQTT command for the correct table.

This is specifically designed to avoid relying on screenshots or customer-provided payment messages.

## Environment-based client configuration

Client-specific production values are not part of source code. Copy `.env.example` to a private production environment and configure:

- M-Pesa environment
- Paybill/shortcode
- Daraja consumer key/secret/passkey
- M-Pesa callback URL
- MySQL connection
- MQTT broker and credentials
- MQTT topic prefix

The same codebase can therefore be deployed for different pool businesses without changing application source code.

## Development modes

Use mock payment behavior while building automated tests, or Safaricom sandbox credentials for integration testing. Production uses environment secrets supplied by the hosting platform.

```text
PAYMENT_MODE=mock
```

No real client credentials belong in GitHub.

## CI/CD foundation

GitHub Actions runs on pushes and pull requests to `main` and `develop` and currently provides:

- PHP syntax validation
- Next.js install/lint/build validation when the frontend exists
- Environment-file/secret tracking guard

Recommended branch flow:

```text
feature/* → develop → CI → main → production
```

Production deployment should be enabled only after the application passes CI and the hosting environment has been configured with client secrets.

## Architecture

```text
Customer
  │
  ├── QR / Smartphone ───────┐
  │                           │
  └── SIM Toolkit / USSD ─────┤
                              ▼
                       Next.js frontend
                              │ HTTPS
                              ▼
                         PHP REST API
                         │    │    │
                         │    │    └── M-Pesa / Daraja
                         │    └────── MySQL
                         └─────────── MQTT / HiveMQ Cloud
                                          │
                                          ▼
                                  External ESP32/controller
                                          │
                                          ▼
                                    Pool hardware
```

## Repository layout

```text
pool-management-system/
├── backend/                 # PHP API
├── frontend/                # Next.js application
├── database/                # MySQL schema and seed data
├── docs/                    # Architecture and integration contracts
├── .github/workflows/       # CI/CD automation
├── .env.example             # Safe configuration template
├── .gitignore
└── README.md
```

## Important deployment rule

When the client provides their real M-Pesa Paybill and Daraja credentials, those values are added to the hosting platform's environment/secrets configuration. The application source does not need to be edited just because the client changes.
