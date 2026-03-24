<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:30px 10px">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)">

  <!-- Header -->
  <tr><td style="background:#1e3a5f;padding:24px 32px">
    <p style="margin:0;color:#fff;font-size:20px;font-weight:bold">Diamond Insurance Broker</p>
    <p style="margin:4px 0 0;color:#93c5fd;font-size:12px;letter-spacing:1px;text-transform:uppercase">Human Resources</p>
  </td></tr>

  <!-- Status banner -->
  <tr><td style="background:{{ $action==='approved' ? '#10b981' : ($action==='rejected' ? '#ef4444' : '#f59e0b') }};padding:14px 32px">
    <p style="margin:0;color:#fff;font-size:15px;font-weight:bold;text-transform:uppercase;letter-spacing:.5px">
      Leave Request {{ ucfirst($action) }}
    </p>
  </td></tr>

  <!-- Body -->
  <tr><td style="padding:28px 32px">
    <p style="margin:0 0 16px;color:#1a1a2e;font-size:15px">
      Dear <strong>{{ $leave->employee?->first_name }}</strong>,
    </p>

    @if($action === 'approved')
    <p style="color:#374151;font-size:14px;line-height:1.6">
      Your leave request has been <strong style="color:#10b981">approved</strong>. Details below:
    </p>
    @elseif($action === 'rejected')
    <p style="color:#374151;font-size:14px;line-height:1.6">
      We regret to inform you that your leave request has been <strong style="color:#ef4444">rejected</strong>.
      @if($leave->rejection_reason)
        <br><strong>Reason:</strong> {{ $leave->rejection_reason }}
      @endif
    </p>
    @else
    <p style="color:#374151;font-size:14px;line-height:1.6">
      Your leave request has been <strong>submitted</strong> and is pending approval.
    </p>
    @endif

    <!-- Details table -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
      <tr style="background:#f9fafb">
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;letter-spacing:.05em;width:40%">Reference</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;font-weight:600">{{ $leave->reference ?? '—' }}</td>
      </tr>
      <tr><td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;letter-spacing:.05em;border-top:1px solid #e5e7eb">Leave Type</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">{{ $leave->leaveType?->name ?? '—' }}</td>
      </tr>
      <tr style="background:#f9fafb"><td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;letter-spacing:.05em;border-top:1px solid #e5e7eb">From</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">{{ \Carbon\Carbon::parse($leave->start_date)->format('d M Y') }}</td>
      </tr>
      <tr><td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;letter-spacing:.05em;border-top:1px solid #e5e7eb">To</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">{{ \Carbon\Carbon::parse($leave->end_date)->format('d M Y') }}</td>
      </tr>
      <tr style="background:#f9fafb"><td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;letter-spacing:.05em;border-top:1px solid #e5e7eb">Duration</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;font-weight:600;border-top:1px solid #e5e7eb">{{ $leave->total_days }} day(s)</td>
      </tr>
    </table>

    <p style="color:#6b7280;font-size:13px;margin:0">
      If you have questions, please contact HR at <a href="mailto:hr@diamond-insurance.com.sa" style="color:#1e3a5f">hr@diamond-insurance.com.sa</a>.
    </p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="margin:0;color:#9ca3af;font-size:11px">
      Diamond Insurance Broker &bull; Riyadh, Saudi Arabia &bull; This is an automated message, please do not reply.
    </p>
  </td></tr>
</table>
</td></tr></table>
</body>
</html>
