<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:30px 10px">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)">

  <tr><td style="background:#1e3a5f;padding:24px 32px">
    <p style="margin:0;color:#fff;font-size:20px;font-weight:bold">Diamond Insurance Broker</p>
    <p style="margin:4px 0 0;color:#93c5fd;font-size:12px;letter-spacing:1px;text-transform:uppercase">Payroll Department</p>
  </td></tr>

  <tr><td style="background:#10b981;padding:14px 32px">
    <p style="margin:0;color:#fff;font-size:15px;font-weight:bold">Payslip Ready — {{ $payslip->payroll?->month }}</p>
  </td></tr>

  <tr><td style="padding:28px 32px">
    <p style="margin:0 0 16px;color:#1a1a2e;font-size:15px">
      Dear <strong>{{ $payslip->employee?->first_name }}</strong>,
    </p>
    <p style="color:#374151;font-size:14px;line-height:1.6;margin:0 0 20px">
      Your payslip for <strong>{{ $payslip->payroll?->month }}</strong> is attached to this email as a PDF.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px">
      <tr style="background:#f9fafb">
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;width:50%">Employee</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;font-weight:600">{{ $payslip->employee?->first_name }} {{ $payslip->employee?->last_name }}</td>
      </tr>
      <tr><td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;border-top:1px solid #e5e7eb">Net Salary</td>
        <td style="padding:10px 16px;font-size:16px;color:#10b981;font-weight:bold;border-top:1px solid #e5e7eb">SAR {{ number_format($payslip->net_salary, 2) }}</td>
      </tr>
      <tr style="background:#f9fafb"><td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;border-top:1px solid #e5e7eb">Period</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">{{ $payslip->payroll?->month }}</td>
      </tr>
    </table>

    <p style="color:#6b7280;font-size:13px;margin:0">
      Please find your detailed payslip attached. Contact HR for any queries: <a href="mailto:hr@diamond-insurance.com.sa" style="color:#1e3a5f">hr@diamond-insurance.com.sa</a>
    </p>
  </td></tr>

  <tr><td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="margin:0;color:#9ca3af;font-size:11px">Diamond Insurance Broker &bull; Confidential — Do not forward</p>
  </td></tr>
</table>
</td></tr></table>
</body>
</html>
