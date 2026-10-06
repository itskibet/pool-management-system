# Security

## Current controls

- Secrets are kept in environment variables and excluded from source control.
- The API uses prepared PDO statements.
- M-Pesa callbacks are resolved server-side from the registered Paybill.
- Payment transaction IDs are unique.
- Table state is re-checked under a database row lock before creating a game.
- The frontend cannot mark a payment confirmed or directly publish MQTT commands.
- MQTT credentials are intended to remain server-side.
- CORS is restricted to the configured frontend origin.

## Required before production

- Add authentication and role-based authorization to all management routes.
- Add organization context from the authenticated user instead of relying on DEFAULT_ORGANIZATION_ID.
- Use HTTPS only.
- Register and verify the M-Pesa validation and confirmation URLs with Safaricom.
- Add rate limiting and request logging around public payment callbacks.
- Store production M-Pesa/MQTT credentials only in the hosting secret store.
- Use authenticated MQTT over TLS and validate controller acknowledgements.
- Keep audit records for payment, game and unlock/lock operations.
- Add database backups and a tested restore procedure.
- Do not expose PHP source directories as public web roots; only the backend public entry point should be web-accessible.
