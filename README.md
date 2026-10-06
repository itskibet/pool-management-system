# Pool Management System

PoolPilot is a software platform for managing pool tables, M-Pesa payments, game sessions and external table controllers.

## Stack

- Frontend: Next.js + TypeScript
- Backend: Plain PHP REST API
- Database: MySQL 8+
- Payments: Safaricom M-Pesa Daraja C2B Paybill
- Messaging: MQTT / HiveMQ Cloud
- Hardware: External ESP32/controller; hardware design is outside this repository
- Frontend hosting: Vercel-compatible Next.js deployment
- Backend hosting: PHP/MySQL hosting or a PHP-capable VPS

## Product rules

1. A customer pays for one pool game/session, not for a fixed amount of time.
2. The customer pays through M-Pesa Lipa na M-Pesa → Pay Bill.
3. The customer enters the hall's Paybill number, the table reference and the configured game price.
4. The PoolPilot website never asks the customer to type a phone number and never treats a browser click as proof of payment.
5. Only a server-side verified M-Pesa transaction can create a game and queue an unlock command.
6. An attendant ends the game manually.
7. Game duration is recorded for reporting and audit purposes only. There is no automatic time-limit lock.
8. When a game is ended, the backend queues a lock command and makes the table available again.
9. QR codes and STK Push are optional future payment methods; they are not part of the primary customer flow.

## Customer-to-table flow

Customer
  │ M-Pesa Pay Bill: Paybill + TABLE03 + Amount
  ▼
Safaricom
  │ C2B validation / confirmation
  ▼
PoolPilot PHP API
  ├── Verify Paybill → organization
  ├── Verify table reference
  ├── Verify exact game price
  ├── Reject duplicate transaction
  └── Create payment + game atomically
              │
              ▼
        MQTT command queue
              │
              ▼
      External controller
              │
              ▼
        Unlock / lock table

## Multi-client model

Each pool business is an organization. Organizations have isolated users, roles, tables, prices, payments, games, M-Pesa configuration, MQTT command records and audit records.

The domain is independent of the Paybill. Changing a client's Paybill is a configuration change.

The current schema intentionally enforces one Paybill per organization configuration. If two businesses share one Paybill, the callback cannot safely identify the organization from the Paybill alone; that routing model requires an explicit shared-Paybill reference scheme before it should be enabled.

## Roles

- Owner: full business control
- Administrator/Manager: operational and configuration management
- Attendant: table and game operations
- Accountant: payment and reporting access

Authentication and authorization are a required next production layer before exposing management endpoints to the public internet. The current foundation uses a configured organization context for local development; it must not be treated as production tenant isolation by itself.

## API

Public/system routes:
- GET /api/health
- POST /api/mpesa/c2b/validation
- POST /api/mpesa/c2b/confirmation

Current management routes:
- GET /api/tables
- GET /api/tables/{id}
- GET /api/payments
- GET /api/games
- POST /api/games/{id}/end
- GET /api/payment-settings

Management routes are currently intended for the local/admin application and should be placed behind authentication before production deployment.

## Local development

1. Copy backend/.env.example to backend/.env.
2. Create/import the MySQL database.
3. For a fresh database, run database/schema.sql, then database/seed.sql.
4. For an existing v0.2 database, back it up and run database/migrations/001_multi_client_paybill.sql once.
5. Set DEFAULT_ORGANIZATION_ID=1 for local development.
6. Set FRONTEND_URL=http://localhost:3000.
7. Start the PHP API from backend/.
8. Start the Next.js application from frontend/.

Do not run the fresh schema against an existing database.

## Environment and secrets

Never commit backend/.env, M-Pesa consumer secrets/passkeys, MQTT passwords or production database passwords. Use environment variables or the hosting provider's secret store.

## Production status

The repository currently provides the application foundation and integration contracts. Before a real customer deployment, complete and test authentication and role-based authorization, production M-Pesa Daraja registration and callbacks, C2B payload validation, MQTT broker authentication and publishing, external controller firmware and acknowledgements, HTTPS, monitoring, backups and recovery procedures.
