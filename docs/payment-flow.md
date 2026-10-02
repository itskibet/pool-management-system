# Payment Flow

## STK Push
1. Customer selects a table and enters a phone number.
2. Frontend requests an STK transaction from the backend.
3. Backend creates a pending payment record.
4. Daraja sends the callback to the backend.
5. Backend validates the callback and payment amount/reference.
6. Payment becomes `confirmed`.
7. Backend creates a game and publishes the MQTT unlock command.

## Paybill
1. Customer is shown the business Paybill and a table/payment reference.
2. M-Pesa processes the payment.
3. The backend receives/obtains the authoritative transaction confirmation using the configured Safaricom integration.
4. Backend matches the reference to the table and verifies amount.
5. Payment becomes `confirmed`.
6. Backend creates a game and publishes the MQTT unlock command.

The frontend must never mark a payment as confirmed by itself.
