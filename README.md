# Pool Management System

Production-oriented software platform for pool-table payments and table control.

## Architecture
- **Frontend:** Next.js + TypeScript, deployable to Vercel
- **Backend:** Plain PHP REST API
- **Database:** MySQL
- **Payments:** Safaricom M-Pesa Daraja (STK Push + Paybill transaction flow)
- **Messaging:** MQTT / HiveMQ Cloud
- **Hardware integration:** External ESP32/controller; hardware is outside this repository

## Core flow
Customer scans table QR → selects payment method → M-Pesa payment is independently verified → payment is stored → backend publishes an MQTT unlock command → external controller handles the physical table.

> Never unlock a table based only on a client-side "I've paid" action.

## Repository
See `docs/architecture.md`, `docs/payment-flow.md`, and `docs/mqtt-integration.md` for integration contracts.

## Local development
Focal development: configure MySQL and copy backend `.env.example` to `.env`.

## Current payment model

The product uses a **Paybill-first, game/session-based flow** for the Kenyan market:

1. Customer arrives at a table.
2. The table displays the client's Paybill number and its table account/reference.
3. Customer uses M-Pesa **Lipa na M-Pesa → Pay Bill** and enters the Paybill, table reference, amount and PIN.
4. Safaricom sends the transaction to the backend C2B confirmation endpoint.
5. The backend validates the Paybill, table reference, amount and transaction uniqueness.
6. A confirmed payment creates one active game session and queues an MQTT UNLOCK command.
7. An attendant ends the game manually; duration is recorded for reporting only.
8. Ending the game queues an MQTT LOCK command and returns the table to available status.

QR codes and STK Push are not required for the primary customer flow. They can be added later as optional payment methods.

## Multi-client model

Each pool business is an organization with its own tables, users, pricing and M-Pesa settings. The Paybill is configuration data, not part of the domain name or application code. User roles are owner, admin, attendant and accountant.

## Existing database upgrade

For an existing local v0.2 database, back up the database and run database/migrations/001_multi_client_paybill.sql once after pulling the repository. Fresh installations should use database/schema.sql followed by database/seed.sql.
