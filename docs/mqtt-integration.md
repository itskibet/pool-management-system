# MQTT Integration

The PHP backend is the MQTT publisher. The external ESP32/controller is the consumer.

## Authorization

The backend queues an UNLOCK only after a verified payment creates an active game. The backend queues LOCK when an authorized attendant ends that game.

The browser never publishes directly to MQTT.

## Topic contract

Each table has one dedicated command topic:

    pool/{table}/command

Examples:

    pool/Table_01/command
    pool/Table_02/command

The topic identifies the destination. The payload identifies the action.

### Unlock payload

    {
      "command": "UNLOCK",
      "payment_id": 152,
      "game_id": 91,
      "timestamp": "2026-10-06T12:30:00+03:00"
    }

### Lock payload

    {
      "command": "LOCK",
      "game_id": 91,
      "timestamp": "2026-10-06T13:02:00+03:00"
    }

Do not create separate /unlock and /lock topics. Keeping commands on one dedicated command channel makes the routing contract stable while the payload carries the action.

## Controller requirements

- Subscribe only to the command topic assigned to the controller/table.
- Use authenticated broker credentials.
- Use TLS.
- Validate command structure before acting.
- Reject commands for unknown table/controller identity.
- Make command handling idempotent where possible.
- Do not treat an old retained command as a fresh authorization to unlock.
- Publish device status and heartbeat on separate future topics.
- Report command acknowledgement/failure on a separate future response topic so queued commands can be reconciled.
- The controller is external to this repository and is not yet implemented here.

## Security boundary

The backend is the authorization boundary. A browser/client request must never directly unlock a table.

The topic structure deliberately separates command traffic from future telemetry/status traffic, while contextual command information remains in the payload.
