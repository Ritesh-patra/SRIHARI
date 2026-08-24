# SEAS — Smart Energy Audit System

## Split (important)

| Platform | Who | What |
|----------|-----|------|
| **Web** (`backend/`) | Admin / Super Admin only | Users, Masters, Hierarchy, Reports, Settings |
| **Flutter** (`mobile/`) | Field Executive · Manager · Project Manager | DTR Survey, Consumer Survey, Approvals, Pipeline |

## Web Admin

```bash
cd backend
php artisan serve
```

Open http://127.0.0.1:8000/login  
`admin@seas.test` / `password` (or `super@seas.test`)

Field emails (`surveyor@seas.test`, `manager@seas.test`, …) are **blocked on web** → redirected to mobile-only page.

## Flutter Field App

1. Install Flutter SDK  
2. `cd mobile && flutter create .` (generates android/ios folders once)  
3. Set API URL in `lib/core/api_config.dart`  
4. `flutter pub get && flutter run`

Demo: `surveyor@seas.test` / `password`

## Mobile API (Sanctum)

Base: `http://127.0.0.1:8000/api`

- `POST /login` → `{ token, user }`
- `GET /dashboard` · `/surveys` · `/approvals` · `/consumer/approved`
- Hierarchy: `/hierarchy/regions` … `/dtrs`
