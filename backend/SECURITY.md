# Backend security rollout notes

## PayHero callback authentication

The PayHero callback endpoint must not process payment events unless the URL token is valid.

1. Generate a unique 32-byte random secret for this PoolPilot deployment. Do not reuse the PayHero API password or commit the secret to Git.
2. Set `PAYHERO_CALLBACK_SECRET` in the backend environment to the generated base64url value (43 characters, no padding).
3. Set `PAYHERO_CALLBACK_URL` to the full HTTPS URL ending in `/api/payhero/callback/<same-secret>`.
4. Configure the exact same URL in the backend environment used when creating each PayHero payment. The controller sends this URL to PayHero as the callback URL.
5. Confirm the hosting/web-server access logs do not retain callback URL paths containing the secret. If the host cannot redact these paths, use a reverse-proxy rule to redact the token or ask PayHero whether it supports a signed callback header.
6. Test a valid callback in a non-production environment; verify that a missing/wrong token cannot confirm a payment or queue an MQTT unlock command. Also test duplicate callbacks and failed payments.
7. Keep the secret only in the host's environment/secrets configuration and local `.env`. Rotate it immediately if it may have been exposed; update the callback URL and environment together.

The route intentionally rejects the old public `/api/payhero/callback` path. Update `PAYHERO_CALLBACK_URL` before enabling this code in any environment that receives real payments.

## Existing Safaricom Daraja C2B integration boundary

- This repository currently does not register the legacy `MpesaController.php` validation/confirmation functions in `backend/routes/api.php`. The active provider path in this branch is the token-protected PayHero offline Paybill flow.
- Do not change the existing live Daraja C2B validation/confirmation URLs or point them directly at this app as part of deployment. The existing integration must remain untouched until a shared, authenticated dispatcher is designed and tested with the business's current system.
- Before production payments, confirm which callback provider will confirm PoolPilot payments. If direct Daraja C2B is required, implement a dispatcher that preserves the existing system's callback behavior and routes transactions using a stable account-reference prefix or other verified discriminator. Test both systems in staging before updating any live Daraja registration.
- Never treat an unverified client-submitted M-Pesa receipt as proof of payment. Only trusted provider callbacks or a verified reconciliation process may confirm a payment and queue an unlock.

## Deployment security checklist

- Run a currently patched PHP release. For the PHP 8.4 branch, install at least PHP 8.4.27 as of 2026-10-25; test compatibility before choosing PHP 8.5.
- Use a dedicated, least-privilege MySQL account. Do not use `root` for the deployed application.
- Keep `.env`, API credentials, callback secret, database passwords, and MQTT credentials out of Git and frontend variables.
- Serve the API only over HTTPS. Configure the web server/reverse proxy to pass HTTPS state correctly; do not trust arbitrary client-supplied forwarded-protocol headers.
- Apply `database/migrations/20261026_create_auth_rate_limits.sql` before deploying this revision to an existing database; fresh databases created from `database/schema.sql` already include the table. Login attempts are limited by IP and IP+account buckets (15-minute windows). Monitor and periodically prune stale `auth_rate_limits` rows.
- Browser state-changing requests are checked against `FRONTEND_URL` when an `Origin` header is present. Keep the frontend origin exact and HTTPS in production; retain `SameSite=Lax`, `HttpOnly`, and production `Secure` session cookies. Test this behind the real reverse proxy.
- Initial-owner creation uses a MySQL advisory lock and rejects passwords shorter than 12 characters. Set a unique, high-entropy `BOOTSTRAP_KEY`, use it only during setup, then remove/rotate it after the owner account exists.
- Use a dedicated, least-privilege MySQL account. Do not use `root` for the deployed application.
- Ensure all tenant-scoped queries include the authenticated user's organization. Restrict game-ending, user-management, organization, and billing actions by role.
- Run the PHP syntax checks and full payment-to-game-to-MQTT tests after merging. Test with PayHero's real callback format in a staging environment before processing live payments.
- Operate the MQTT publisher under a supervisor or reliable scheduler. The worker takes a MySQL advisory lock to prevent concurrent publisher processes; still make device command handling idempotent because network delivery and process failure can create uncertainty about whether a command reached a device.
