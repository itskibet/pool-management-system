# MQTT Integration

The PHP backend is the MQTT publisher. The external ESP32/controller is the consumer.

## Authorization

The backend queues an UNLOCK only after a verified payment creates an active game. The backend queues LOCK when an authorized attendant ends that game.

The browser never publishes directly to MQTT.

## Topic contract

Current table topic format:

- pool/{table}/unlock

The backend may publish both UNLOCK and LOCK command payloads on the configured table topic. The command field determines the action.

Example:

    {
      "command": "UNLOCK",
      "payment_id": 152,
      "game_id": 91,
      "timestamp": "2026-10-06T12:30:00+03:00"
    }

    {
      "command": "LOCK",
      "game_id": 91,
      "timestamp": "2026-10-06T13:02:00+03:00"
    }

## Controller requirements

- Use authenticated broker credentials.
- Use TLS.
- Validate command structure before acting.
- Reject commands for unknown table/controller identity.
- Make command handling idempotent where possible.
- Publish device status and heartbeat separately.
- Report command acknowledgement/failure so queued commands can be reconciled.

The controller is external to this repository and is not yet implemented here.
