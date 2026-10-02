# Architecture

```text
Customer
  ↓
Next.js / Vercel
  ↓ HTTPS
PHP REST API
  ├── MySQL
  ├── M-Pesa Daraja
  └── MQTT / HiveMQ Cloud
             ↓
        External ESP32/controller
             ↓
        Existing pool hardware
```

The repository owns the software and integration contract. Physical hardware design is external.
