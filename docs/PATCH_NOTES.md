# HRMS — Fix Patch Documentation

**Patch version:** `1.1.0`  
**Date:** 2026-03-24  
**Target repo:** `github.com/Jithineyadiyil/HRMS`  
**Stack:** Laravel 10 · PHP 8.2 · Angular 20 · NgRx 19

---

## Table of contents

1. [Critical fixes](#1-critical-fixes)
2. [Architecture improvements](#2-architecture-improvements)
3. [Security hardening](#3-security-hardening)
4. [Test coverage added](#4-test-coverage-added)
5. [Installation instructions](#5-installation-instructions)
6. [File manifest](#6-file-manifest)

---

## 1. Critical fixes

### 1.1 Missing `/contracts` module — runtime crash (FIXED)

**Problem:** `app-routing.module.ts` registered `path: 'contracts'` pointing to
`ContractsModule`, but the directory `frontend/src/app/modules/contracts/` did
not exist. Any navigation to `/contracts` threw an unhandled `ChunkLoadError`.

**Fix:** Created:
- `frontend/src/app/modules/contracts/contracts.module.ts`
- `frontend/src/app/modules/contracts/components/contract-list.component.ts`

The component renders a placeholder "coming soon" panel. Replace it with
the full contracts feature when ready.

---

### 1.2 Hardcoded temporary password (FIXED)

**Problem:** `EmployeeController::store()` hardcoded the literal string
`"Password@123"` as every new employee's password and returned it in the
API response. This is an OWASP A02 violation (Cryptographic Failures).

**Fix:** `EmployeeController.php` now calls `Str::password(12, true, true, false)`
which uses PHP's CSPRNG to generate a 12-character random password with
letters, numbers and symbols. Each employee creation yields a unique,
unpredictable temporary password.

```php
// BEFORE (INSECURE)
'password' => Hash::make('Password@123')

// AFTER (SECURE)
'password' => Hash::make(Str::password(12, true, true, false))
```

---

### 1.3 No rate limiting on auth endpoints (FIXED)

**Problem:** `POST /api/v1/auth/login` and `POST /api/v1/auth/forgot-password`
had no throttle middleware, making them trivially brute-forceable.

**Fix:** Applied Laravel's built-in `throttle` middleware at the route level:

| Endpoint             | Limit              |
|---|---|
| `POST auth/login`           | 10 req / minute   |
| `POST auth/forgot-password` | 5 req / minute    |
| `POST auth/reset-password`  | 5 req / minute    |
| `POST jobs/{id}/apply`      | 10 req / minute   |
| All protected routes        | 300 req / minute  |

Returns `429 Too Many Requests` when exceeded.

---

## 2. Architecture improvements

### 2.1 Repository pattern (NEW)

**Problem:** Controllers and Services called Eloquent models directly, mixing
persistence and business logic. Unit testing required a real database.

**Added files:**
- `app/Repositories/Contracts/EmployeeRepositoryInterface.php` — contract
- `app/Repositories/EmployeeRepository.php` — Eloquent implementation
- `app/Providers/RepositoryServiceProvider.php` — DI binding

**EmployeeRepository features:**
- `paginate(array $filters)` — filtered, sorted, paginated list
- `findById(int $id)` — with full relations
- `create(array $data)` — create with return
- `update(Employee, array $data)` — update with fresh model
- `terminate(Employee)` — status + soft-delete + token revoke
- `nextEmployeeCode()` — collision-safe using `lockForUpdate()` inside a transaction

**Register the provider** in `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\RepositoryServiceProvider::class,
],
```

---

### 2.2 API Resources (NEW)

**Problem:** All controllers returned raw Eloquent JSON via `response()->json($model)`.
Sensitive fields leaked unless manually excluded. No single wire-format definition existed.

**Fix:** `app/Http/Resources/EmployeeResource.php` transforms all Employee
responses. Key behaviours:

- `salary`, `bank_account`, `bank_name`, `national_id` are `null` unless the
  authenticated user has `super_admin`, `hr_manager`, or `finance_manager` role.
- Relations are conditionally included via `whenLoaded()` — no N+1 queries.
- All date fields are explicitly serialised as `Y-m-d` strings.

---

### 2.3 FormRequest validation (NEW)

**Problem:** Validation was inline in controller methods (`$request->validate([...])`)
violating Single Responsibility and making rules untestable independently.

**Added files:**
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Requests/Employee/StoreEmployeeRequest.php`
- `app/Http/Requests/Employee/UpdateEmployeeRequest.php`

`StoreEmployeeRequest` includes `authorize()` that gates creation to HR roles.
`UpdateEmployeeRequest` allows employees to edit their own profile.

---

### 2.4 Angular typed models (NEW)

**Problem:** The entire Angular codebase used `any` for employee state,
store selectors, HTTP responses, and component inputs.

**Fix:** `frontend/src/app/shared/models/employee.model.ts` exports:
- `Employee` interface matching `EmployeeResource::toArray()`
- `PaginatedResponse<T>` generic wrapper
- `EmployeeFilters`, `CreateEmployeeDto`, `UpdateEmployeeDto`
- Literal union types: `EmployeeStatus`, `EmploymentType`, `Gender`, `MaritalStatus`

---

### 2.5 Angular service layer (NEW)

**Problem:** NgRx Effects called `HttpClient` directly, coupling transport
to state management.

**Fix:** `frontend/src/app/core/services/employee.service.ts` is the single HTTP
gateway. Effects now call `this.employeeService.method()` only.

---

### 2.6 Typed NgRx store (UPDATED)

All three store files (`actions`, `reducer`, `effects`) are updated:

- Zero `any` types — all props use `Employee`, `EmployeeFilters`, `PaginatedResponse<Employee>`
- `Pagination` interface typed explicitly
- Per-action failure actions (`createEmployeeFailure`, etc.) instead of reusing `loadEmployeesFailure`
- Effects show error toasts on failure; success effects navigate the router

---

### 2.7 Laravel Scheduler replaces manual cron setup (FIXED)

**Problem:** `LEAVE_ACCRUAL_SETUP.md` instructed developers to configure
Windows Task Scheduler with hardcoded XAMPP paths — unmaintainable in production.

**Fix:** `app/Console/Kernel.php` now schedules:
```php
$schedule->command('leave:accrue')->weekdays()->at('00:05');
```

Add one cron entry to the server:
```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

### 2.8 `declare(strict_types=1)` and PHPDoc (ADDED)

All new/updated PHP files include:
- `declare(strict_types=1)` at the top
- Full PHPDoc on every class and public method (`@param`, `@return`, `@throws`)

---

## 3. Security hardening

| # | Severity | Finding | Fix Applied |
|---|---|---|---|
| 1 | 🔴 Critical | Hardcoded `Password@123` | CSPRNG via `Str::password()` |
| 2 | 🔴 Critical | No rate limit on login | `throttle:10,1` middleware |
| 3 | 🔴 Critical | No rate limit on forgot-password | `throttle:5,1` middleware |
| 4 | 🟡 High | Raw Eloquent in API responses | `EmployeeResource` transforms all output |
| 5 | 🟡 High | Sensitive fields (`salary`, `bank_account`) always exposed | Role-gated in `EmployeeResource` |
| 6 | 🟡 High | Employee code race condition | `lockForUpdate()` transaction |
| 7 | 🟡 Medium | `changePassword` didn't revoke other sessions | Other-device tokens now revoked |
| 8 | 🟡 Medium | `resetPassword` didn't revoke all tokens | All tokens revoked on reset |

---

## 4. Test coverage added

### Backend (PHPUnit)

| File | Tests | Coverage |
|---|---|---|
| `tests/Feature/AuthApiTest.php` | 10 | Login, logout, me, changePassword, rate limiting |
| `tests/Feature/EmployeeApiTest.php` | 15 | CRUD, permissions, sensitive fields, rate limiting |
| `tests/Unit/EmployeeServiceTest.php` | 4 | Leave allocations, onboarding tasks |

### Frontend (Jest / Jasmine)

| File | Tests |
|---|---|
| `employee-list.component.spec.ts` | 17 (init, filters, navigation, helpers, teardown) |
| `employee.service.spec.ts` | 9 (getAll, getOne, create, update, delete, export) |

---

## 5. Installation instructions

### Backend

```bash
cd HRMS/backend

# 1. Copy new files into their positions (see File Manifest below)

# 2. Register the Repository provider in config/app.php
#    Add to the 'providers' array:
#    App\Providers\RepositoryServiceProvider::class,

# 3. Regenerate autoload
composer dump-autoload

# 4. Run tests to verify
php artisan test
```

### Frontend

```bash
cd HRMS/frontend

# 1. Copy new files into their positions (see File Manifest below)

# 2. Run unit tests
ng test --watch=false
```

---

## 6. File manifest

### Backend — new files

```
backend/
├── app/
│   ├── Console/
│   │   └── Kernel.php                                          (UPDATED)
│   ├── Http/
│   │   ├── Controllers/API/
│   │   │   ├── AuthController.php                             (UPDATED)
│   │   │   └── EmployeeController.php                         (UPDATED)
│   │   ├── Requests/
│   │   │   ├── Auth/
│   │   │   │   └── LoginRequest.php                           (NEW)
│   │   │   └── Employee/
│   │   │       ├── StoreEmployeeRequest.php                   (NEW)
│   │   │       └── UpdateEmployeeRequest.php                  (NEW)
│   │   └── Resources/
│   │       └── EmployeeResource.php                           (NEW)
│   ├── Providers/
│   │   └── RepositoryServiceProvider.php                      (NEW)
│   ├── Repositories/
│   │   ├── Contracts/
│   │   │   └── EmployeeRepositoryInterface.php                (NEW)
│   │   └── EmployeeRepository.php                             (NEW)
│   └── Services/
│       └── EmployeeService.php                                (UPDATED)
├── routes/
│   └── api.php                                                (UPDATED)
└── tests/
    ├── Feature/
    │   ├── AuthApiTest.php                                    (NEW)
    │   └── EmployeeApiTest.php                                (NEW)
    └── Unit/
        └── EmployeeServiceTest.php                            (NEW)
```

### Frontend — new files

```
frontend/src/app/
├── core/services/
│   ├── employee.service.ts                                    (NEW)
│   └── employee.service.spec.ts                              (NEW)
├── modules/
│   ├── contracts/
│   │   ├── contracts.module.ts                               (NEW — crash fix)
│   │   └── components/
│   │       └── contract-list.component.ts                    (NEW)
│   └── employees/
│       ├── components/
│       │   ├── employee-list.component.ts                    (UPDATED)
│       │   └── employee-list.component.spec.ts               (NEW)
│       └── store/
│           ├── employee.actions.ts                           (UPDATED)
│           ├── employee.effects.ts                           (UPDATED)
│           └── employee.reducer.ts                           (UPDATED)
└── shared/models/
    └── employee.model.ts                                     (NEW)
```
