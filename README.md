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

## New laptop (from GitHub)

Code is on GitHub. Local `.env`, database, and photos are **not** in git — restore those from the `SEAS-LAPTOP-BACKUP` zip (see `RESTORE.txt` inside the zip).

```bash
git clone https://github.com/Ritesh-patra/SRIHARI.git
cd SRIHARI/backend
copy .env.example .env
composer install
php artisan key:generate
npm install && npm run build
```

Then import `seas-dump.sql` into MySQL/MariaDB database `seas`, copy `.env` from the backup zip (keeps the same APP_KEY), copy `storage/app/public` photos, run `php artisan storage:link`, then `php artisan serve`.

Flutter:

```bash
cd mobile
flutter pub get
flutter run
```

## Mobile API (Sanctum)

Base: `http://127.0.0.1:8000/api`

- `POST /login` → `{ token, user }`
- `GET /dashboard` · `/surveys` · `/approvals` · `/consumer/approved`
- Hierarchy: `/hierarchy/regions` … `/dtrs`
