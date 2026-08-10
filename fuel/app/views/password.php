<section class="maw900 m-0_auto">
	<h1 class="m0 mb24 fs30">パスワード変更</h1>
	<form class="p32 ba-surface bo1-border br10" method="post" action="<?php echo e(\Uri::create('account/password')); ?>">
		<?php echo \Form::csrf(); ?>
		<?php
		// パスワード変更で表示する入力欄。
		$fields = array(
			'current_password' => '現在のパスワード',
			'password' => '新しいパスワード',
			'password_confirmation' => '新しいパスワード（確認）',
		);
		foreach ($fields as $name => $label):
		?>
			<label class="d-grid g8 fw600 mb22">
				<span><?php echo e($label); ?></span>
				<input name="<?php echo e($name); ?>" type="password" autocomplete="<?php echo $name === 'current_password' ? 'current-password' : 'new-password'; ?>" required>
			</label>
		<?php endforeach; ?>
		<p class="mb22 c-muted fs13">新しいパスワードは12バイト以上72バイト以下で入力してください。</p>
		<div class="d-flex jc-flex_end">
			<button class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover" type="submit">変更する</button>
		</div>
	</form>
</section>
