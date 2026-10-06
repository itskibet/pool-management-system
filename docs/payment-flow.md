# Payment Flow

## Primary customer flow: M-Pesa Paybill

The production customer experience is Paybill-first. The customer does not need to scan a QR code and does not enter a phone number into PoolPilot.

1. Customer arrives at an available table.
2. The table displays the client's Paybill number, table reference and game price.
3. Customer opens M-Pesa and chooses Lipa na M-Pesa → Pay Bill.
4. Customer enters the displayed Paybill number.
5. Customer enters the table account/reference.
6. Customer enters the configured game amount and completes the M-Pesa PIN step.
7. Safaricom sends the C2B validation request when validation is configured.
8. PoolPilot identifies the organization from the registered Paybill, resolves the table reference and checks the configured price and table availability.
9. Safaricom sends the authoritative C2B confirmation.
10. PoolPilot re-checks the transaction, locks the table row during processing, rejects duplicate transaction IDs and records the payment.
11. If the table is still available, the same database transaction creates one active game and queues one MQTT UNLOCK command.
12. The external controller processes the authenticated command and unlocks the table.
13. The customer plays without an automatic time limit.
14. An authorized attendant ends the game manually.
15. PoolPilot records the duration, marks the table available and queues MQTT LOCK.

## Payment integrity

The backend is the payment authorization boundary.

- The Paybill identifies the organization.
- The account/reference identifies the table within that organization.
- The amount must match the table's configured session price.
- Transaction IDs are unique.
- Confirmation processing locks the table row to prevent two concurrent callbacks from creating two active games.
- Payment confirmation and game creation are atomic.
- A duplicate callback is treated as already processed.
- If a payment is confirmed after the table has become unavailable, the payment is recorded but no automatic unlock is created; the transaction requires operational reconciliation.

## Security boundary

The frontend never confirms a payment and never directly unlocks a table. The physical controller should act only on authenticated MQTT commands issued by the backend.

## Other payment methods

QR codes and STK Push are not required for the primary flow. The database retains an STK Push payment type for a future optional integration.

## Important production note

The exact C2B callback fields and Daraja registration behavior must be tested against Safaricom's current production integration before go-live. The current implementation is the application-side contract, not proof that a live Paybill is already registered.
