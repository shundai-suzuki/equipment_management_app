<section class="w520 p42 ba-surface bo1-border br10">
	<h1 class="m0 mb32 fs30 ta-center">ログイン</h1>
	<form method="post" action="<?php echo e(\Uri::create('login')); ?>">
		<?php echo \Form::csrf(); ?>
		<label class="d-grid g8 fw600 mb22">
			<span>社員番号</span>
			<input name="employee_number" type="text" inputmode="numeric" autocomplete="username" required>
		</label>
		<label class="d-grid g8 fw600 mb22">
			<span>パスワード</span>
			<input name="password" type="password" autocomplete="current-password" required>
		</label>
		<button class="w100p p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover" type="submit">ログイン</button>
	</form>
</section>
