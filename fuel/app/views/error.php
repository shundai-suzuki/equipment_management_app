<section class="w720 p54 ta-center ba-surface bo1-border br10">
	<p class="m0 mb12 fs72 fw800"><?php echo e($status); ?></p>
	<h1 class="m0 fs30"><?php echo e($title); ?></h1>
	<p><?php echo e($message); ?></p>
	<?php if ( ! empty($request_id)): ?>
		<p class="c-muted ff-monospace">追跡ID: <?php echo e($request_id); ?></p>
	<?php endif; ?>
</section>
