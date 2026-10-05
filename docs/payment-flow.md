# Payment Flow

The system supports customers with smartphones and customers using feature phones. QR is a convenience, not a requirement.

## 1. QR / smartphone flow

1. Customer selects a table.
2. Frontend displays the table price and QR payment option.
3. A payment session is created with a unique account/reference value.
4. Customer completes M-Pesa payment.
5. Safaricom/Daraja provides the authoritative transaction result to the backend.
6. Backend verifies amount, reference/table, transaction status, and duplicate receipt protection.
7. Payment becomes `confirmed`.
8. Backend creates the game session.
9. Backend publishes the MQTT unlock command for that exact table.
10. External ESP32/controller handles the physical unlock.

## 2. Manual M-Pesa / SIM Toolkit flow

For customers without smartphones:

1. Customer selects the table or asks staff for the table's payment reference.
2. System displays the Paybill instructions and unique account/reference.
3. Customer uses the M-Pesa SIM Toolkit/USSD menu to pay.
4. The authoritative M-Pesa transaction is received/obtained through the configured Safaricom integration.
5. Backend matches the reference to the pending payment session and verifies the exact amount.
6. Payment becomes `confirmed`.
7. Game session is created and the table unlock command is published.

Example customer instructions:

```text
TABLE 05 — KSh 30

Pay using M-Pesa:
1. Open M-Pesa
2. Lipa na M-Pesa
3. Pay Bill
4. Business Number: [CLIENT PAYBILL]
5. Account Number: [GENERATED TABLE/SESSION REFERENCE]
6. Amount: KSh 30
7. Enter your M-Pesa PIN
```

The actual client Paybill is supplied through production configuration; it is never hard-coded into source control.

## 3. Assisted payment

Staff may record an assisted payment only through an authenticated staff workflow. The system records the actor, amount, table, method, and audit event. Staff actions must not bypass the same authorization and audit controls.

## Anti-fraud rules

- Never unlock from a client-side `I've paid` button.
- Never trust a customer screenshot as payment confirmation.
- Verify the authoritative M-Pesa transaction.
- Verify the expected amount.
- Verify the table/session reference.
- Reject duplicate M-Pesa receipts.
- Make payment confirmation idempotent so repeated callbacks cannot create duplicate games or unlocks.
- Log payment and unlock events for auditability.

## Development and production

Development uses `PAYMENT_MODE=mock` or Safaricom sandbox credentials. Production secrets are supplied through the hosting platform environment and are not committed to GitHub.
