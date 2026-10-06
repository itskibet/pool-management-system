# MQTT Protocol

The controller interface is session-based, not time-based.

## Topic structure

Each pool table has one dedicated command topic:

    pool/{table}/command

Examples:

    pool/Table_01/command
    pool/Table_02/command

The topic identifies the destination table. The payload identifies the action.

## Unlock

The backend queues an unlock command only after a Paybill transaction has been verified server-side and a game session has been created.

Example payload:

    {
      "command": "UNLOCK",
      "payment_id": 152,
      "game_id": 91,
      "timestamp": "2026-10-06T12:30:00+03:00"
    }

## Lock

When an attendant ends the game, the backend queues a lock command on the same table command topic.

Example payload:

    {
      "command": "LOCK",
      "game_id": 91,
      "timestamp": "2026-10-06T13:02:00+03:00"
    }

The controller subscribes only to the command topic assigned to its table/controller identity. It must validate the command payload before acting and must not unlock from a client-side request. The backend is the authorization boundary.

A game's duration is recorded for reporting only. There is no automatic time-limit lock in the current product model.

The command topic is intentionally separate from future device telemetry, state, and acknowledgement topics.
