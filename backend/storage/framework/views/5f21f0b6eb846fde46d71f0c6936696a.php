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

<h3 class="subject">EMPLOYMENT CERTIFICATE</h3>

<p>This is to certify that <strong><?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?></strong>
bearing Iqama/ID No. <strong><?php echo e($employee->national_id ?? $employee->iqama_number ?? '—'); ?></strong>
is currently employed with <strong>Diamond Insurance Broker</strong> in the capacity of
<strong><?php echo e($employee->designation?->title ?? '—'); ?></strong> in the
<strong><?php echo e($employee->department?->name ?? '—'); ?></strong> Department.</p>

<table class="data-table">
  <tr><td>Employee Name</td><td><?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?></td></tr>
  <tr><td>Employee Code</td><td><?php echo e($employee->employee_code); ?></td></tr>
  <tr><td>Position</td><td><?php echo e($employee->designation?->title ?? '—'); ?></td></tr>
  <tr><td>Department</td><td><?php echo e($employee->department?->name ?? '—'); ?></td></tr>
  <tr><td>Employment Since</td><td><?php echo e($hire_date); ?></td></tr>
  <tr><td>Employment Type</td><td><?php echo e(ucfirst(str_replace('_',' ', $employee->employment_type ?? 'Full Time'))); ?></td></tr>
  <tr><td>Nationality</td><td><?php echo e($employee->nationality ?? '—'); ?></td></tr>
</table>

<p>This certificate is issued as per his/her request for <strong><?php echo e($purpose ?: 'official'); ?></strong> purposes and we wish him/her all the best.</p>

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

<?php echo $__env->make('letters.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\xamp new\htdocs\HRMS\backend\resources\views/letters/employment.blade.php ENDPATH**/ ?>