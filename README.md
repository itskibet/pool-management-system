# Pool Management System

Production-oriented software platform for paid pool-table sessions, payment verification, table control, and operational reporting.

## Technology

- **Frontend:** Next.js + TypeScript, deployable to Vercel
- **Backend:** Plain PHP REST API
- **Database:** MySQL
- **Payments:** Safaricom M-Pesa / Daraja C2B
- **Messaging:** MQTT / HiveMQ Cloud
- **Hardware:** External ESP32/controller connected to the existing pool hardware
- **CI:** GitHub Actions

## Payment model

The system deliberately supports customers with and without smartphones:

1. **QR / smartphone payment** — the application creates a payment session and is designed to use Safaricom Dynamic QR for the smartphone flow once the client's Daraja credentials are configured.
2. **Manual M-Pesa / SIM Toolkit** — customer uses the normal M-Pesa menu/USSD flow with the client's Paybill and the unique table/session reference.
3. **Assisted payment** — reserved for authenticated staff workflows.

QR is optional. A customer must not need a smartphone to use the system.

## Anti-fraud principle

The frontend never decides that money has been paid. A table is unlocked only after the backend receives an authoritative M-Pesa C2B confirmation, verifies the expected amount and table/session reference, prevents duplicate receipts, creates the game session, queues the MQTT unlock command, and marks the table as playing.

This is specifically designed to avoid relying on screenshots or customer-provided payment messages.

## Environment-based client configuration

Client-specific production values are not part of source code. Copy `backend/.env.example` to a private production environment and configure:

- M-Pesa environment
- Paybill/shortcode
- Daraja consumer key/secret
- M-Pesa callback and validation URLs
- MySQL connection
- MQTT broker and credentials
- MQTT topic prefix
- Frontend allowed origin

The same codebase can therefore be deployed for different pool businesses without changing application source code.

## Development modes

Use mock callback payloads while building automated tests, or Safaricom sandbox credentials for integration testing. Production uses environment secrets supplied by the hosting platform.

```text
PAYMENT_MODE=mock
```

No real client credentials belong in GitHub.

## MQTT outbox

A successful verified payment writes an `unlock` command to `mqtt_commands` inside the same database transaction as the payment and game. The worker then publishes queued commands to MQTT and records the publish result.

Run the worker from cPanel/cron after configuring Composer dependencies:

```text
*/1 * * * * /usr/local/bin/php /home/USERNAME/public_html/backend/cron/publish_mqtt_commands.php
```

Adjust the PHP and project paths to the hosting account.

## CI/CD foundation

GitHub Actions runs on pushes and pull requests and provides:

- PHP syntax validation
- Composer manifest/dependency validation
- Next.js install/lint/build validation
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
                         │    │    └── M-Pesa / Daraja C2B
                         │    └────── MySQL
                         └────────── MQTT / HiveMQ Cloud
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
├── backend/                 # PHP API, services and cron worker
├── frontend/                # Next.js application
├── database/                # MySQL schema and migrations
├── docs/                    # Architecture and integration contracts
├── .github/workflows/       # CI/CD automation
├── .env.example             # Safe configuration template
├── .gitignore
└── README.md
```

## Important deployment rule

When the client provides their real M-Pesa Paybill and Daraja credentials, those values are added to the hosting platform's environment/secrets configuration. The application source does not need to be edited just because the client changes.
