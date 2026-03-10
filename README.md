# HRMS — HR Management System

```
HRMS/
├── backend/    ← Laravel 10 API (PHP 8.2)
└── frontend/   ← Angular 20 SPA
```

## Quick Start

### Backend
```bash
cd HRMS/backend
# Edit .env: set DB_DATABASE=hrms_db, DB_USERNAME=root, DB_PASSWORD=
php artisan migrate
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve --port=8000
```
**Login:** admin@hrms.com / Admin@1234

### Frontend
```bash
cd HRMS/frontend
npm install
ng serve --port 4200
```
Open: http://localhost:4200
