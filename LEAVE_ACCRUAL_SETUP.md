# Annual Leave Accrual — Setup Guide

## Policy
- **Working days:** Sunday – Thursday
- **Entitlement:**
  - Less than 5 years of service → **22 working days/year**
  - 5 or more years of service  → **30 working days/year**
- **Accrual:** Daily, calculated from the employee's hire date anniversary

## How It Works
Days accrue proportionally each working day:
- < 5 years: `22 / 260 = 0.0846 days` per working day
- ≥ 5 years: `30 / 260 = 0.1154 days` per working day

The accrual year resets on the employee's hire date anniversary each year.

---

## Step 1 — Run the Migration
```bash
cd D:\xamp new\htdocs\HRMS\backend
php artisan migrate
```

## Step 2 — Run Initial Accrual (catch up all existing employees)
```bash
php artisan leave:accrue
```
Preview first without saving:
```bash
php artisan leave:accrue --dry-run
```

## Step 3 — Set Up Windows Task Scheduler (runs daily at midnight)

Since XAMPP is on Windows, use **Task Scheduler** instead of cron:

1. Open **Task Scheduler** → Create Basic Task
2. **Name:** HRMS Leave Accrual
3. **Trigger:** Daily at 00:05 AM
4. **Action:** Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `D:\xamp new\htdocs\HRMS\backend\artisan leave:accrue`
   - Start in: `D:\xamp new\htdocs\HRMS\backend`
5. Save

OR run this PowerShell command once (as Administrator):
```powershell
$action  = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" `
           -Argument "D:\xamp new\htdocs\HRMS\backend\artisan leave:accrue" `
           -WorkingDirectory "D:\xamp new\htdocs\HRMS\backend"
$trigger = New-ScheduledTaskTrigger -Daily -At "00:05AM"
Register-ScheduledTask -TaskName "HRMS_LeaveAccrual" -Action $action -Trigger $trigger -RunLevel Highest
```

## Manual Trigger from Frontend (HR Panel)
POST `/api/v1/leave/accrue` — runs the accrual immediately for all employees.

---

## Accrual Table
| Months Worked | < 5 Years | ≥ 5 Years |
|---|---|---|
| 1 month (~22 days) | 1.85 days | 2.54 days |
| 3 months | 5.50 days | 7.50 days |
| 6 months | 11.0 days | 15.0 days |
| 12 months | 22.0 days | 30.0 days |
