# Docker setup

## Local run

1. Copy `.env.docker.example` to `.env.docker` and fill secrets.
2. Start containers:

```powershell
Copy-Item .env.docker.example .env.docker
docker compose --env-file .env.docker up -d --build
```

3. Open the API through Nginx at `http://localhost:8080`.

## Notes

- The `app` container runs Laravel PHP-FPM.
- The `nginx` container serves `public/`.
- The `queue` container runs `php artisan queue:work`.
- The `scheduler` container runs `php artisan schedule:run` every minute.
- Database is MySQL 8.4 with a persistent volume.
- Only the `app` container runs migrations on startup.

## Demo on the internet

See [docs/DEMO_CLOUDFLARE_TUNNEL.md](../docs/DEMO_CLOUDFLARE_TUNNEL.md) for a free demo setup using Cloudflare Tunnel.
