# Deployment

## Frontend
Deploy `frontend/` to Vercel. Configure the backend base URL as an environment variable.

## Backend
Deploy `backend/` to PHP hosting with HTTPS, Composer dependencies, PHP environment variables, and a MySQL database.

## MQTT
Use HiveMQ Cloud or another managed MQTT broker. Keep credentials server-side.

## Database
Run `database/schema.sql`, then `database/seed.sql` only for initial development data.
