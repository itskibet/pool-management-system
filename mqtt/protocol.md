# MQTT Protocol

Unlock messages should be JSON and traceable to a verified payment/game.

```json
{
  "command": "UNLOCK",
  "payment_id": 152,
  "game_id": 91,
  "timestamp": "2026-10-02T12:30:00+03:00"
}
```

The backend must publish only after server-side payment verification.
