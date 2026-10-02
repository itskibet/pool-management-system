# MQTT Integration

The PHP backend is the MQTT publisher. The external ESP32/controller is the consumer.

Minimum contract:
- Subscribe controller to `pool/{table}/unlock`.
- Accept only authenticated broker connections.
- Validate message structure before acting.
- Publish device status and heartbeat separately.
