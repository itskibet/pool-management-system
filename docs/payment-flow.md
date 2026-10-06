# Payment Flow

## Primary customer flow: M-Pesa Paybill

The production customer experience is Paybill-first. The customer does not need to scan a QR code and does not enter a phone number into PoolPilot.

1. Customer arrives at a table.
2. The table displays the client's Paybill number, table account/reference and game price.
3. Customer opens M-Pesa and chooses Lipa na M-Pesa → Pay Bill.
4. Customer enters the displayed Paybill number.
5. Customer enters the table account/reference.
6. Customer enters the game amount and completes the M-Pesa PIN step.
7. Safaricom sends the authoritative C2B transaction to the backend confirmation/validation integration.
8. Backend identifies the client from the configured Paybill, validates the table reference, expected amount and transaction uniqueness.
9. Backend records the payment as confirmed.
10. Backend creates one active game session and queues an MQTT UNLOCK command.
11. The external controller unlocks the table.
12. The customer plays without an automatic time limit.
13. An authorized attendant ends the game manually.
14. Backend records the duration for reporting, marks the table available and queues MQTT LOCK.

### Security boundary

The frontend never confirms a payment and never directly unlocks a table. The physical controller should act only on authenticated MQTT commands issued by the backend.

### Other payment methods

QR codes and STK Push are not required for the primary flow. The database retains support for stk_push so an optional STK flow can be added later without redesigning the payment model.

### Important payment rules

- A Paybill transaction must belong to the configured client.
- The account/reference must resolve to a table belonging to that client.
- The amount must match the table's configured game price.
- M-Pesa transaction identifiers must be unique.
- A table already in an active game must not receive another automatic unlock.
- Payment confirmation and session creation should be atomic.
