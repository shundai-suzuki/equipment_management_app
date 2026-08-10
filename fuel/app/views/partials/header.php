<header class="h68 d-flex ai-center jc-space_between p0_28 c-white ba-green">
	<a class="c-white fs24 fw700 td-none" href="<?php echo \Uri::create($guest ? 'login' : 'dashboard'); ?>">社内備品管理</a>
	<?php if ( ! $guest): ?>
		<div class="d-flex ai-center g20 fw600">
			<span><?php echo $is_admin ? '管理者' : '社員'; ?></span>
			<form class="m0" method="post" action="<?php echo e(\Uri::create('logout')); ?>">
				<?php echo \Form::csrf(); ?>
				<button class="p8_16 c-white ba-transparent bo1-white br6" type="submit">ログアウト</button>
			</form>
		</div>
	<?php endif; ?>
</header>
