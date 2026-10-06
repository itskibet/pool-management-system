# MQTT Protocol

The controller interface is session-based, not time-based.

## Unlock

The backend queues an unlock command only after a Paybill transaction has been verified server-side and a game session has been created.

```json
{
  "command": "UNLOCK",
  "payment_id": 152,
  "game_id": 91,
  "timestamp": "2026-10-06T12:30:00+03:00"
}
```

## Lock

When an attendant ends the game, the backend queues a lock command.

```json
{
  "command": "LOCK",
  "game_id": 91,
  "timestamp": "2026-10-06T13:02:00+03:00"
}
```

The controller must not unlock from a client-side request. The backend is the authorization boundary.

A game's duration is recorded for reporting only. There is no automatic time-limit lock in the current product model.
