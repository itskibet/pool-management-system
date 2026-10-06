# Deployment

## Frontend

Deploy the Next.js frontend to Vercel or another Node-compatible host.

Configure:

    NEXT_PUBLIC_API_URL=https://api.your-domain.example

The backend URL must use HTTPS in production.

## Backend

Deploy the backend to PHP hosting or a PHP-capable VPS.

Requirements:

- PHP 8.2+
- MySQL 8+
- Composer
- HTTPS
- Environment variable/secret support

Only the backend public entry point should be exposed by the web server. Keep config, controllers, database scripts and .env outside the public document root where the hosting setup permits.

## Database

Fresh installation:

1. Run database/schema.sql.
2. Run database/seed.sql.
3. Replace development placeholder settings.
4. Create real business configuration.

Existing v0.2 installation:

1. Back up the database.
2. Run database/migrations/001_multi_client_paybill.sql once.
3. Verify organization, table and payment counts.
4. Verify the Paybill configuration before enabling callbacks.

Never run the fresh schema over an existing production database.

## M-Pesa

Register the production C2B validation and confirmation URLs with Safaricom Daraja. Test the real callback payload and response contract before accepting customer payments.

## MQTT

Use HiveMQ Cloud or another managed MQTT broker. Store broker credentials only on the backend. The controller must authenticate to the broker over TLS.

## Go-live checks

- Management authentication enabled
- Organization isolation tested
- Production Paybill verified
- HTTPS working
- C2B callbacks tested
- MQTT publish and controller acknowledgement tested
- Database backup and restore tested
- Monitoring and error logging enabled
