<?php $__env->startSection('content'); ?>
<div class="ref-line">
  <span>Ref: <?php echo e($ref); ?></span>
  <span>Date: <?php echo e($date); ?></span>
</div>

<?php if($to_name): ?>
<div class="to-block">
  <div class="to-label">To</div>
  <div class="to-name"><?php echo e($to_name); ?></div>
</div>
<?php else: ?>
<p>To Whom It May Concern,</p>
<?php endif; ?>

<h3 class="subject">NO OBJECTION CERTIFICATE (NOC)</h3>

<p>This is to certify that <strong><?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?></strong>,
<?php echo e($employee->designation?->title ?? 'Employee'); ?> in our <?php echo e($employee->department?->name ?? ''); ?> Department,
bearing Iqama/ID No. <strong><?php echo e($employee->national_id ?? $employee->iqama_number ?? '—'); ?></strong>,
has been employed with Diamond Insurance Broker since <strong><?php echo e($hire_date); ?></strong>.</p>

<table class="data-table">
  <tr><td>Employee Name</td><td><?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?></td></tr>
  <tr><td>Employee Code</td><td><?php echo e($employee->employee_code); ?></td></tr>
  <tr><td>Position</td><td><?php echo e($employee->designation?->title ?? '—'); ?></td></tr>
  <tr><td>Nationality</td><td><?php echo e($employee->nationality ?? '—'); ?></td></tr>
  <tr><td>Iqama / ID No.</td><td><?php echo e($employee->national_id ?? $employee->iqama_number ?? '—'); ?></td></tr>
</table>

<p>We, <strong>Diamond Insurance Broker</strong>, have <strong>no objection</strong> to
<?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?>

<?php if($purpose): ?> <?php echo e($purpose); ?>. <?php else: ?> proceeding with his/her personal matter. <?php endif; ?></p>

<p>This NOC is issued in good faith and without any responsibility or liability on our part.</p>

<div class="signature-block">
  <div class="sig-line"></div>
  <div class="sig-name">HR Manager</div>
  <div class="sig-title">Human Resources Department</div>
  <div class="sig-company">Diamond Insurance Broker</div>
</div>

<div class="stamp-area">
  <div class="stamp-box">Official Stamp</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('letters.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\xamp new\htdocs\HRMS\backend\resources\views/letters/noc.blade.php ENDPATH**/ ?>