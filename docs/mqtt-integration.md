# MQTT Integration

The PHP backend is the MQTT publisher. The external ESP32/controller is the consumer. Physical hardware remains outside this repository.

## Topic contract

Unlock topic:

```text
pool/{TABLE_CODE}/unlock
```

Example:

```text
pool/TABLE05/unlock
```

Recommended payload:

```json
{
  "command": "unlock",
  "table": "TABLE05",
  "payment_id": 123,
  "game_id": 456,
  "issued_at": "2026-10-05T12:00:00Z",
  "nonce": "server-generated-id"
}
```

## Security requirements

- Use TLS for production MQTT connections.
- Use broker authentication; never expose anonymous production publishing.
- Keep MQTT credentials in environment/hosting secrets.
- Validate topic and payload on the controller.
- Reject commands for unknown tables.
- Make unlock commands short-lived and idempotent where practical.
- Record every publish attempt and result in `mqtt_commands`.
- Publish device status/heartbeat on separate topics.

## Unlock rule

An unlock command is created only after the backend has independently confirmed a valid payment and created the corresponding game session. The frontend cannot directly publish an unlock command.
