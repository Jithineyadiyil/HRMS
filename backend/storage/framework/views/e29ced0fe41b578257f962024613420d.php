<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size:10.5pt; color:#1a1a2e; background:#fff; line-height:1.6; }

  /* Letterhead */
  .letterhead { border-bottom:3px solid #1e3a5f; padding-bottom:14px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:flex-end; }
  .lh-left    { }
  .lh-logo    { font-size:17pt; font-weight:bold; color:#1e3a5f; letter-spacing:0.5px; }
  .lh-company { font-size:8pt; color:#555; margin-top:2px; }
  .lh-contact { text-align:right; font-size:8pt; color:#555; line-height:1.8; }
  .lh-ref     { font-size:8pt; color:#888; margin-bottom:4px; }

  /* Body */
  .ref-line  { display:flex; justify-content:space-between; margin-bottom:20px; font-size:9pt; color:#555; }
  .to-block  { margin-bottom:16px; }
  .to-label  { font-size:9pt; color:#888; text-transform:uppercase; letter-spacing:.06em; }
  .to-name   { font-size:12pt; font-weight:bold; color:#1e3a5f; margin-top:2px; }
  .to-title  { font-size:9pt; color:#555; }

  h3.subject { font-size:12pt; font-weight:bold; color:#1e3a5f; text-decoration:underline; margin:16px 0 14px; }

  p { margin-bottom:10px; text-align:justify; }

  /* Data table */
  .data-table { width:100%; border-collapse:collapse; margin:14px 0; }
  .data-table td { padding:6px 10px; border:1px solid #ddd; font-size:9.5pt; }
  .data-table td:first-child { background:#f5f7ff; font-weight:600; color:#1e3a5f; width:38%; }

  /* Signature */
  .signature-block { margin-top:36px; }
  .sig-line  { width:180px; border-top:1px solid #333; margin-top:42px; margin-bottom:4px; }
  .sig-name  { font-weight:bold; font-size:10pt; }
  .sig-title { font-size:9pt; color:#555; }
  .sig-company { font-size:9pt; color:#555; }

  /* Footer */
  .page-footer { position:fixed; bottom:0; left:0; right:0; text-align:center; font-size:7.5pt; color:#aaa; border-top:1px solid #eee; padding:5px; background:#fff; }

  /* Stamp area */
  .stamp-area { display:flex; justify-content:space-between; margin-top:30px; }
  .stamp-box  { border:1px dashed #ccc; width:150px; height:80px; display:flex; align-items:center; justify-content:center; font-size:8pt; color:#bbb; text-align:center; }
</style>
</head>
<body>
<div class="letterhead">
  <div class="lh-left">
    <div class="lh-logo">Diamond Insurance Broker</div>
    <div class="lh-company">شركة دايموند للتأمين</div>
  </div>
  <div class="lh-contact">
    Riyadh, Kingdom of Saudi Arabia<br>
    Tel: +966 11 XXX XXXX<br>
    info@diamond-insurance.com.sa
  </div>
</div>
<?php echo $__env->yieldContent('content'); ?>
<div class="page-footer">
  Diamond Insurance Broker &nbsp;|&nbsp; Riyadh, Saudi Arabia &nbsp;|&nbsp; Confidential
</div>
</body>
</html>
<?php /**PATH D:\xamp new\htdocs\HRMS\backend\resources\views/letters/layout.blade.php ENDPATH**/ ?>