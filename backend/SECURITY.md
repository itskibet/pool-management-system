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

## Deployment security checklist

- Run a currently patched PHP release. For the PHP 8.4 branch, install at least PHP 8.4.27 as of 2026-10-25; test compatibility before choosing PHP 8.5.
- Use a dedicated, least-privilege MySQL account. Do not use `root` for the deployed application.
- Keep `.env`, API credentials, callback secret, database passwords, and MQTT credentials out of Git and frontend variables.
- Serve the API only over HTTPS. Configure the web server/reverse proxy to pass HTTPS state correctly; do not trust arbitrary client-supplied forwarded-protocol headers.
- Add rate limiting to login and setup endpoints, CSRF protection or strict same-origin checks for cookie-authenticated mutations, and an atomic one-time bootstrap mechanism before public deployment.
- Ensure all tenant-scoped queries include the authenticated user's organization. Restrict game-ending, user-management, organization, and billing actions by role.
- Run the PHP syntax checks and full payment-to-game-to-MQTT tests after merging. Test with PayHero's real callback format in a staging environment before processing live payments.
- Operate the MQTT publisher under a supervisor or reliable scheduler, prevent concurrent workers from claiming the same queued command, and make device command handling idempotent.
