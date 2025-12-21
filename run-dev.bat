@echo off
REM Seed membership tiers (safe to re-run) and backfill user tiers
php artisan db:seed --class=MembershipTiersSeeder
php artisan users:backfill-membership-tiers

REM Launch Laravel scheduler in a separate window
start "laravel-scheduler" cmd /c "php artisan schedule:work"

REM Start Laravel development server
php artisan serve --host=localhost --port=8000
