# HRMS Backend — Laravel 10

## Setup (3 steps)

### 1. Configure .env
Edit `.env` file — set your XAMPP MySQL credentials:
```
DB_DATABASE=hrms_db
DB_USERNAME=root
DB_PASSWORD=          ← blank for XAMPP default
```

### 2. Create database + run migrations
```bash
# Create DB in phpMyAdmin, then:
php artisan migrate
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
php artisan db:seed
php artisan storage:link
```

### 3. Start server
```bash
php artisan serve --port=8000
```

## Login
```
Email:    admin@hrms.com
Password: Admin@1234
```

## API Base URL
```
http://localhost:8000/api/v1/
```
