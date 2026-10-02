# Security

- Never commit `.env` or M-Pesa/MQTT credentials.
- Use HTTPS for all public endpoints.
- Verify M-Pesa callbacks server-side.
- Never trust payment status from the browser.
- Use prepared SQL statements.
- Hash administrator passwords with PHP `password_hash()`.
- Validate and rate-limit public payment endpoints.
- Use authenticated MQTT connections and TLS.
- Keep audit logs for payment and unlock operations.
