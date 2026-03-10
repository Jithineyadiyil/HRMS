<?php
namespace App\Services;

use App\Models\Separation;
use App\Models\OffboardingTemplate;
use App\Models\OffboardingItem;
use App\Models\Employee;
use Carbon\Carbon;

class SeparationService
{
    // ── Generate reference ────────────────────────────────────────────────
    public function generateReference(): string
    {
        $year  = now()->year;
        $count = Separation::whereYear('created_at', $year)->count() + 1;
        return 'SEP-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    // ── Calculate Saudi gratuity (Article 84 Labor Law) ───────────────────
    // - < 2 years:   0
    // - 2–5 years:   half-month per year
    // - > 5 years:   1 month per year (full)
    // For termination without cause: full month per year from day 1
    public function calculateGratuity(Employee $emp, string $type, string $lastWorkingDay): float
    {
        if (!$emp->hire_date) return 0;

        $hireDate = Carbon::parse($emp->hire_date);
        $endDate  = Carbon::parse($lastWorkingDay);
        $years    = $hireDate->floatDiffInYears($endDate);

        if ($years < 2) return 0;

        $monthlySalary = (float)($emp->salary ?? 0);
        $dailyRate     = $monthlySalary / 30;

        if ($type === 'termination') {
            // Full entitlement: 1 month per year from day 1
            return round($monthlySalary * $years, 2);
        }

        // Resignation / retirement / mutual
        if ($years <= 5) {
            return round(($monthlySalary / 2) * $years, 2);
        }
        // First 5 years: half month; beyond 5: full month
        return round((($monthlySalary / 2) * 5) + ($monthlySalary * ($years - 5)), 2);
    }

    // ── Calculate leave encashment ────────────────────────────────────────
    public function calculateLeaveEncashment(Employee $emp): float
    {
        $allocation = $emp->leaveAllocations()
            ->whereHas('leaveType', fn($q) => $q->where('code','AL'))
            ->where('year', now()->year)
            ->first();

        if (!$allocation) return 0;

        $remainingDays = max(0, (float)$allocation->remaining_days);
        $dailyRate     = ((float)($emp->salary ?? 0)) / 30;
        return round($remainingDays * $dailyRate, 2);
    }

    // ── Generate offboarding checklist from templates ─────────────────────
    public function generateChecklist(Separation $sep): void
    {
        $templates = OffboardingTemplate::where('is_active', true)->orderBy('sort_order')->get();
        $items = $templates->map(fn($t) => [
            'separation_id' => $sep->id,
            'template_id'   => $t->id,
            'title'         => $t->title,
            'category'      => $t->category,
            'is_required'   => $t->is_required,
            'status'        => 'pending',
            'sort_order'    => $t->sort_order,
            'created_at'    => now(),
            'updated_at'    => now(),
        ])->toArray();

        if (!empty($items)) OffboardingItem::insert($items);
    }

    // ── Stats ──────────────────────────────────────────────────────────────
    public function stats(): array
    {
        return [
            'pending_manager'  => Separation::where('status','pending_manager')->count(),
            'pending_hr'       => Separation::where('status','pending_hr')->count(),
            'offboarding'      => Separation::where('status','offboarding')->count(),
            'completed_ytd'    => Separation::where('status','completed')->whereYear('updated_at',now()->year)->count(),
            'by_type'          => Separation::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total','type'),
        ];
    }
}
