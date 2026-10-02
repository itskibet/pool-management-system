# Pool Management API

Plain PHP REST API for the Pool Management System.

## Current foundation

The API currently supports:

- GET /api/health — API + MySQL connectivity check
- GET /api/tables — list pool tables
- GET /api/tables/{id} — get one table
- GET /api/payments — list payment records
- POST /api/payments — create a pending payment record for local testing
- GET /api/games — list games

M-Pesa verification and MQTT publishing are intentionally not enabled yet.

## Local setup

1. Copy .env.example to .env.
2. Configure the MySQL connection.
3. Import ../database/schema.sql.
4. Import ../database/seed.sql.
5. Start the PHP server:

php -S localhost:8000 -t public

Then open:

http://localhost:8000/api/health

Never commit .env or real credentials.
