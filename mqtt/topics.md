# MQTT Topics

- `pool/{table}/unlock` — request the external controller to unlock a table after verified payment.
- `pool/{table}/status  — controller publishes table/device status.
- `pool/{table}/heartbeat` — controller publishes health/heartbeat data.
- `pool/{table}/command` — reserved for future commands.
