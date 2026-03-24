<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRequest;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LetterController extends Controller
{
    // Map request type codes → template names
    private const TEMPLATE_MAP = [
        'DOC_SALARY'    => 'salary',
        'DOC_EMPLOY'    => 'employment',
        'DOC_EXP'       => 'experience',
        'DOC_NOC'       => 'noc',
        'DOC_BANK'      => 'bank',
        'DOC_SALARY_TR' => 'salary_transfer',
        'TRAVEL_LETTER' => 'employment',   // repurpose employment for travel allowance letter
    ];

    private const LETTER_TITLES = [
        'DOC_SALARY'    => 'Salary Certificate',
        'DOC_EMPLOY'    => 'Employment Certificate',
        'DOC_EXP'       => 'Experience Letter',
        'DOC_NOC'       => 'No Objection Certificate',
        'DOC_BANK'      => 'Bank Letter',
        'DOC_SALARY_TR' => 'Salary Transfer Letter',
        'TRAVEL_LETTER' => 'Travel Allowance Letter',
    ];

    /**
     * Generate a PDF letter for a given employee request.
     * GET /api/v1/requests/{id}/generate-letter
     */
    public function generate(Request $request, $id)
    {
        $req      = EmployeeRequest::with(['employee.department','employee.designation','requestType'])->findOrFail($id);
        $typeCode = $req->requestType?->code ?? '';

        if (!isset(self::TEMPLATE_MAP[$typeCode])) {
            return response()->json(['message' => "No letter template available for request type '{$typeCode}'."], 422);
        }

        $employee  = $req->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }
        $employee->load(['department','designation']);
        $employee->makeVisible(['national_id','bank_account']); // needed for letter generation

        $template = self::TEMPLATE_MAP[$typeCode];
        $data     = $this->buildData($req, $employee, $typeCode);

        $pdf = Pdf::loadView("letters.{$template}", $data)
                  ->setPaper('a4')
                  ->setOptions(['dpi' => 150, 'defaultFont' => 'Arial']);

        $title    = self::LETTER_TITLES[$typeCode] ?? 'Letter';
        $filename = strtolower(str_replace(' ', '_', $title))
                  . '_' . ($employee->employee_code ?? $employee->id)
                  . '_' . now()->format('Ymd')
                  . '.pdf';

        // Optionally mark request as in_progress if still pending
        if ($req->status === 'pending') {
            $req->update(['status' => 'in_progress', 'assigned_to' => auth()->id()]);
        }

        return $pdf->download($filename);
    }

    /**
     * Quick-generate letter from employee ID (without needing a request).
     * GET /api/v1/employees/{empId}/letter/{type}
     */
    public function generateDirect(Request $request, $empId, $type)
    {
        if (!isset(self::TEMPLATE_MAP[$type])) {
            return response()->json(['message' => "Unknown letter type '{$type}'."], 422);
        }

        $employee = Employee::with(['department','designation'])->findOrFail($empId);
        $employee->makeVisible(['national_id','bank_account']); // needed for letter generation
        $data     = $this->buildDirectData($employee, $type, $request->all());

        $template = self::TEMPLATE_MAP[$type];
        $pdf      = Pdf::loadView("letters.{$template}", $data)
                      ->setPaper('a4')
                      ->setOptions(['dpi' => 150, 'defaultFont' => 'Arial']);

        $title    = self::LETTER_TITLES[$type] ?? 'Letter';
        $filename = strtolower(str_replace(' ', '_', $title))
                  . '_' . $employee->employee_code
                  . '_' . now()->format('Ymd')
                  . '.pdf';

        return $pdf->download($filename);
    }

    // ── Data builders ─────────────────────────────────────────────────────

    private function buildData(EmployeeRequest $req, Employee $employee, string $typeCode): array
    {
        // Parse details field for purpose/to_name hints
        $details  = $req->details ?? '';
        $purpose  = $this->extractPurpose($details);
        $toName   = $this->extractToName($details);
        $bankName = $this->extractBankName($details);
        $accountNo= $this->extractAccountNo($details);

        return array_merge(
            $this->baseData($employee, $req->reference ?? 'HR-' . $req->id),
            [
                'purpose'    => $purpose,
                'to_name'    => $toName,
                'bank_name'  => $bankName,
                'account_no' => $accountNo,
            ]
        );
    }

    private function buildDirectData(Employee $employee, string $type, array $params): array
    {
        return array_merge(
            $this->baseData($employee, 'HR-' . strtoupper($type) . '-' . now()->format('Ymd')),
            [
                'purpose'    => $params['purpose']    ?? '',
                'to_name'    => $params['to_name']    ?? '',
                'bank_name'  => $params['bank_name']  ?? ($employee->bank_name ?? ''),
                'account_no' => $params['account_no'] ?? ($employee->bank_account ?? ''),
            ]
        );
    }

    private function baseData(Employee $employee, string $ref): array
    {
        $hireDate  = $employee->hire_date ? Carbon::parse($employee->hire_date)->format('d F Y') : '—';
        $endDate   = $employee->termination_date ? Carbon::parse($employee->termination_date)->format('d F Y') : null;

        // Salary components
        $basic     = (float)($employee->salary ?? 0);
        $housing   = $employee->housing_allowance !== null
            ? (float)$employee->housing_allowance
            : round($basic * 0.25, 2);
        $transport = $employee->transport_allowance !== null
            ? (float)$employee->transport_allowance
            : 400.00;
        $gross     = $basic + $housing + $transport
                   + (float)($employee->mobile_allowance ?? 0)
                   + (float)($employee->food_allowance   ?? 0)
                   + (float)($employee->other_allowances ?? 0);

        // Experience duration
        $start    = Carbon::parse($employee->hire_date ?? now());
        $end      = $employee->termination_date ? Carbon::parse($employee->termination_date) : now();
        $years    = $start->diffInYears($end);
        $months   = $start->copy()->addYears($years)->diffInMonths($end);
        $experienceYears = $years > 0
            ? "{$years} year" . ($years > 1 ? 's' : '') . ($months > 0 ? " {$months} month" . ($months > 1 ? 's' : '') : '')
            : ($months > 0 ? "{$months} month" . ($months > 1 ? 's' : '') : 'Less than 1 month');

        return [
            'employee'         => $employee,
            'ref'              => $ref,
            'hire_date'        => $hireDate,          // blade uses $hire_date
            'end_date'         => $endDate,           // blade uses $end_date
            'experience_years' => $experienceYears,   // blade uses $experience_years
            'basic'            => $basic,
            'housing'          => $housing,
            'transport'        => $transport,
            'gross'            => $gross,
            'date'             => now()->format('d F Y'),
            // Defaults — overridden by buildData/buildDirectData
            'to_name'          => '',
            'purpose'          => '',
            'bank_name'        => $employee->bank_name ?? '',
            'account_no'       => $employee->bank_account ?? '',
        ];
    }

    // ── Simple text extractors for free-text "details" field ─────────────

    private function extractPurpose(string $text): string
    {
        if (preg_match('/purpose[:\s]+([^.]+)/i', $text, $m)) return trim($m[1]);
        if (preg_match('/for\s+(bank|embassy|visa|loan|personal|official)[a-z\s]*/i', $text, $m)) return trim($m[0]);
        return '';
    }

    private function extractToName(string $text): string
    {
        if (preg_match('/to[:\s]+([A-Z][^\n.]+)/i', $text, $m)) return trim($m[1]);
        if (preg_match('/addressed to[:\s]+([^\n.]+)/i', $text, $m)) return trim($m[1]);
        return '';
    }

    private function extractBankName(string $text): string
    {
        if (preg_match('/bank[:\s]+([^\n,]+)/i', $text, $m)) return trim($m[1]);
        $banks = ['Al Rajhi','SNB','NCB','Riyad Bank','Alinma','ANB','SABB','Banque Saudi Fransi'];
        foreach ($banks as $b) {
            if (stripos($text, $b) !== false) return $b;
        }
        return '';
    }

    private function extractAccountNo(string $text): string
    {
        if (preg_match('/account[^:]*[:s]+([A-Z0-9\s]{8,26})/i', $text, $m)) return trim($m[1]);
        if (preg_match('/IBAN[:\s]+([A-Z]{2}[0-9]{2}[A-Z0-9]{4,})/i', $text, $m)) return trim($m[1]);
        return '';
    }
}
