<?php $__env->startSection('content'); ?>
<div class="ref-line">
  <span>Ref: <?php echo e($ref); ?></span>
  <span>Date: <?php echo e($date); ?></span>
</div>

<p>To Whom It May Concern,</p>

<h3 class="subject">SALARY CERTIFICATE</h3>

<p>This is to certify that <strong><?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?></strong>
is a permanent employee of <strong>Diamond Insurance Broker</strong>, and has been employed with us
since <strong><?php echo e($hire_date); ?></strong>.</p>

<p>His/Her current salary details are as follows:</p>

<table class="data-table">
  <tr><td>Employee Name</td><td><?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?></td></tr>
  <tr><td>Employee Code</td><td><?php echo e($employee->employee_code); ?></td></tr>
  <tr><td>Designation</td><td><?php echo e($employee->designation?->title ?? '—'); ?></td></tr>
  <tr><td>Department</td><td><?php echo e($employee->department?->name ?? '—'); ?></td></tr>
  <tr><td>Basic Salary</td><td>SAR <?php echo e(number_format($employee->salary ?? 0, 2)); ?></td></tr>
  <tr><td>Housing Allowance</td><td>SAR <?php echo e(number_format($housing, 2)); ?></td></tr>
  <tr><td>Transport Allowance</td><td>SAR <?php echo e(number_format($transport, 2)); ?></td></tr>
  <?php if(($employee->mobile_allowance ?? 0) > 0): ?>
  <tr><td>Mobile Allowance</td><td>SAR <?php echo e(number_format($employee->mobile_allowance, 2)); ?></td></tr>
  <?php endif; ?>
  <?php if(($employee->food_allowance ?? 0) > 0): ?>
  <tr><td>Food Allowance</td><td>SAR <?php echo e(number_format($employee->food_allowance, 2)); ?></td></tr>
  <?php endif; ?>
  <?php if(($employee->other_allowances ?? 0) > 0): ?>
  <tr><td>Other Allowances</td><td>SAR <?php echo e(number_format($employee->other_allowances, 2)); ?></td></tr>
  <?php endif; ?>
  <tr><td><strong>Total Monthly Salary</strong></td><td><strong>SAR <?php echo e(number_format($gross, 2)); ?></strong></td></tr>
  <tr><td>Nationality</td><td><?php echo e($employee->nationality ?? '—'); ?></td></tr>
  <tr><td>Iqama / ID No.</td><td><?php echo e($employee->national_id ?? $employee->iqama_number ?? '—'); ?></td></tr>
</table>

<?php if($purpose): ?>
<p>This letter is issued as per his/her request for <strong><?php echo e($purpose); ?></strong> purposes.</p>
<?php else: ?>
<p>This letter is issued as per his/her request.</p>
<?php endif; ?>

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

<?php echo $__env->make('letters.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\xamp new\htdocs\HRMS\backend\resources\views/letters/salary.blade.php ENDPATH**/ ?>