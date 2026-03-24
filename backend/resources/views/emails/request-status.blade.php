<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:30px 10px">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)">

  <tr><td style="background:#1e3a5f;padding:24px 32px">
    <p style="margin:0;color:#fff;font-size:20px;font-weight:bold">Diamond Insurance Broker</p>
    <p style="margin:4px 0 0;color:#93c5fd;font-size:12px;letter-spacing:1px;text-transform:uppercase">Human Resources</p>
  </td></tr>

  <tr><td style="background:{{ $status==='completed' ? '#10b981' : ($status==='rejected' ? '#ef4444' : '#3b82f6') }};padding:14px 32px">
    <p style="margin:0;color:#fff;font-size:15px;font-weight:bold;text-transform:uppercase;letter-spacing:.5px">
      Request {{ ucfirst($status) }}
    </p>
  </td></tr>

  <tr><td style="padding:28px 32px">
    <p style="margin:0 0 16px;color:#1a1a2e;font-size:15px">
      Dear <strong>{{ $request->employee?->first_name }}</strong>,
    </p>

    @if($status === 'completed')
    <p style="color:#374151;font-size:14px;line-height:1.6;margin:0 0 20px">
      Your request has been <strong style="color:#10b981">completed</strong>.
      @if($request->completion_notes) <br>{{ $request->completion_notes }} @endif
    </p>
    @elseif($status === 'rejected')
    <p style="color:#374151;font-size:14px;line-height:1.6;margin:0 0 20px">
      Your request has been <strong style="color:#ef4444">rejected</strong>.
      @if($request->rejection_reason) <br><strong>Reason:</strong> {{ $request->rejection_reason }} @endif
    </p>
    @else
    <p style="color:#374151;font-size:14px;line-height:1.6;margin:0 0 20px">
      Your request is now <strong style="color:#3b82f6">being processed</strong> by HR.
    </p>
    @endif

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px">
      <tr style="background:#f9fafb">
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;width:38%">Reference</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;font-weight:600">{{ $request->reference }}</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;border-top:1px solid #e5e7eb">Request Type</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">{{ $request->requestType?->name ?? '—' }}</td>
      </tr>
      <tr style="background:#f9fafb">
        <td style="padding:10px 16px;font-size:12px;color:#6b7280;font-weight:bold;text-transform:uppercase;border-top:1px solid #e5e7eb">Submitted</td>
        <td style="padding:10px 16px;font-size:13px;color:#1a1a2e;border-top:1px solid #e5e7eb">{{ $request->created_at?->format('d M Y') }}</td>
      </tr>
    </table>

    <p style="color:#6b7280;font-size:13px;margin:0">
      Questions? Contact HR: <a href="mailto:hr@diamond-insurance.com.sa" style="color:#1e3a5f">hr@diamond-insurance.com.sa</a>
    </p>
  </td></tr>

  <tr><td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="margin:0;color:#9ca3af;font-size:11px">Diamond Insurance Broker &bull; Riyadh, Saudi Arabia &bull; Automated notification</p>
  </td></tr>
</table>
</td></tr></table>
</body>
</html>
