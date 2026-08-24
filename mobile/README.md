# SEAS Mobile (Flutter)

Field app for **Field Executive · Manager · Project Manager**.

Web Admin portal is separate: `backend/` (Admin / Super Admin only).

## Setup

1. Install [Flutter](https://docs.flutter.dev/get-started/install)
2. Update API base URL in `lib/core/api_config.dart` (your PC LAN IP if testing on phone)
3. Run Laravel API: `cd backend && php artisan serve --host=0.0.0.0 --port=8000`
4. From `mobile/`:

```bash
flutter pub get
flutter run
```

## Demo logins (password: `password`)

| Role | Email |
|------|-------|
| Field Executive | `surveyor@seas.test` |
| Manager | `manager@seas.test` |
| Project Manager | `pm@seas.test` |

## API

- `POST /api/login`
- `GET /api/dashboard`
- `GET /api/surveys`
- `GET /api/approvals`
- `GET /api/consumer/approved`
- Hierarchy cascade under `/api/hierarchy/*`

## Pole map pinning

- **Stack:** `flutter_map` + OpenStreetMap tiles (no Google Maps API key).
- **Add Pole on Map** → drop pin (tap / long-press) → confirm → save pole with lat/lng.
- All DTR poles with coords show as labeled markers; keep adding on the same map.
- **Download Map / Pins** → CSV (coords) + printable Leaflet HTML + GeoJSON.
- Chrome web + Android both supported; allow location in browser when prompted.
