# Cloudflare Tunnel Demo Guide

This guide is for exposing the backend of the movie booking system to the internet for a temporary free demo.

## When to use

- You want friends to try the app without paying for a VPS.
- You want a quick public URL for testing.
- You accept that a free tunnel URL may change unless you use a named tunnel with a real domain.

## What this exposes

This guide exposes the Laravel backend running on `http://localhost:8080` through Cloudflare Tunnel.

If you also want the frontend public, you must expose the frontend separately or run both apps behind the same public origin.

## Prerequisites

- Docker backend already running with `docker compose --env-file .env.docker up -d --build`.
- `cloudflared` installed on the machine that runs the backend.
- A Cloudflare account.

## Quick demo: temporary free URL

Use this if you only need a temporary demo link.

```powershell
cloudflared tunnel --url http://localhost:8080
```

Cloudflare will print a public `trycloudflare.com` URL.

Use that URL as the backend base URL for temporary testing.

### Limitations

- The URL is temporary and can change when you restart the tunnel.
- OAuth and payment callbacks may need the public URL updated.
- If the frontend is on a different origin, CORS must allow that origin.

## Stable demo: named tunnel

Use this if you want a repeatable demo URL.

1. Log in to Cloudflare:

```powershell
cloudflared tunnel login
```

2. Create a tunnel:

```powershell
cloudflared tunnel create movie-booking-demo
```

3. Create a config file, for example `C:\Users\<you>\\.cloudflared\config.yml`:

```yaml
tunnel: movie-booking-demo
credentials-file: C:\Users\<you>\\.cloudflared\\<tunnel-id>.json

ingress:
  - hostname: api.example.com
    service: http://localhost:8080
  - service: http_status:404
```

4. Route DNS to the tunnel:

```powershell
cloudflared tunnel route dns movie-booking-demo api.example.com
```

5. Run the tunnel:

```powershell
cloudflared tunnel run movie-booking-demo
```

## Environment values to update for demo

For a public demo, update these values in `.env.docker` or your production env file:

- `APP_URL`
- `GOOGLE_REDIRECT_URI`
- `PAYPAL_RETURN_URL`
- `PAYPAL_CANCEL_URL`
- `MOMO_RETURN_URL`
- `MOMO_IPN_URL`
- `FRONTEND_REDIRECT_URL` if your frontend uses it

If your frontend is also public, set `VITE_API_BASE_URL` in the frontend to the public backend URL and set `CORS_ALLOWED_ORIGINS` in the backend env to the frontend public origin.

## CORS reminder

The backend CORS config is environment-driven. For a public demo, set `CORS_ALLOWED_ORIGINS` to the demo frontend origin or use a single stable public origin for both frontend and backend.

## Recommended demo flow for this project

1. Start the backend with Docker Compose.
2. Expose the backend using Cloudflare Tunnel.
3. Expose the frontend with a second tunnel or deploy the frontend separately.
4. Point the frontend API base URL to the backend public URL.
5. Update OAuth and payment callback URLs to the public domain or tunnel URL.

## Good fit for this project

This approach is good for a free demo, but not ideal for long-term production because:

- tunnel URLs can change in quick mode,
- payment and OAuth callbacks need stable URLs,
- queue and scheduler still need to stay running locally.
