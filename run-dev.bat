@echo off
REM Launch Laravel scheduler in a separate window
start "laravel-scheduler" cmd /c "php artisan schedule:work"

REM Start Laravel development server
php artisan serve --host=localhost --port=8000
