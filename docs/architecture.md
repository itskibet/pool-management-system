# Architecture

```text
                         CUSTOMER
                    ┌────────┴────────┐
                    │                 │
             Smartphone           Feature phone
                    │                 │
                 QR pay         SIM Toolkit/USSD
                    │                 │
                    └────────┬────────┘
                             ▼
                      Next.js frontend
                             │ HTTPS
                             ▼
                        PHP REST API
                    ┌────────┼─────────┐
                    │        │         │
                  MySQL   M-Pesa     MQTT
                           Daraja   HiveMQ Cloud
                                      │
                                      ▼
                              External ESP32
                                      │
                                      ▼
                                Pool hardware
```

## Responsibility boundaries

- **Frontend:** table selection, QR display, payment instructions, payment status UI.
- **Backend:** authorization, payment-session creation, M-Pesa verification, idempotency, database transactions, game creation, audit records, and MQTT command creation/publishing.
- **MySQL:** authoritative application state and financial/game records.
- **M-Pesa/Daraja:** authoritative payment provider.
- **MQTT:** transport for commands to the external controller.
- **ESP32/controller:** validates commands and controls the physical table hardware.

## Security boundary

The frontend must never be trusted to confirm a payment or unlock a table. Only the backend can transition a valid payment to `confirmed` and create an unlock command.

## Deployment boundary

Application source is independent of client configuration. Production Paybill/shortcode, Daraja credentials, callback URL, database credentials, and MQTT credentials are injected through the hosting platform environment/secrets. They are never committed to GitHub.
