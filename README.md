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
