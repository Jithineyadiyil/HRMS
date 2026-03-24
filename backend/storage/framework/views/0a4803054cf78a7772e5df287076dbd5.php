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

<h3 class="subject">EXPERIENCE LETTER</h3>

<p>This is to certify that <strong><?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?></strong>
bearing Iqama/ID No. <strong><?php echo e($employee->national_id ?? $employee->iqama_number ?? '—'); ?></strong>
has been employed with <strong>Diamond Insurance Broker</strong> from
<strong><?php echo e($hire_date); ?></strong>
<?php if($end_date): ?> to <strong><?php echo e($end_date); ?></strong> <?php else: ?> to date <?php endif; ?>.</p>

<table class="data-table">
  <tr><td>Employee Name</td><td><?php echo e($employee->first_name); ?> <?php echo e($employee->last_name); ?></td></tr>
  <tr><td>Employee Code</td><td><?php echo e($employee->employee_code); ?></td></tr>
  <tr><td>Position Held</td><td><?php echo e($employee->designation?->title ?? '—'); ?></td></tr>
  <tr><td>Department</td><td><?php echo e($employee->department?->name ?? '—'); ?></td></tr>
  <tr><td>Date of Joining</td><td><?php echo e($hire_date); ?></td></tr>
  <tr><td>Last Working Day</td><td><?php echo e($end_date ?: 'Currently Employed'); ?></td></tr>
  <tr><td>Total Experience</td><td><?php echo e($experience_years); ?></td></tr>
</table>

<p>During his/her tenure, <?php echo e($employee->first_name); ?> has proven to be a dedicated and hardworking professional.
We wish him/her continued success in future endeavors.</p>

<p>This letter is issued upon his/her request and without any liability on the part of the company.</p>

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

<?php echo $__env->make('letters.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\xamp new\htdocs\HRMS\backend\resources\views/letters/experience.blade.php ENDPATH**/ ?>