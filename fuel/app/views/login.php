<section class="w520 p42 ba-surface bo1-border br10" data-page="login">
	<h1 class="m0 mb32 fs30 ta-center">ログイン</h1>
	<form data-bind="submit: submit">
		<?php echo Form::csrf(); ?>
		<label class="d-grid g8 fw600 mb22">
			<span>社員番号</span>
			<input
				type="text"
				inputmode="numeric"
				autocomplete="username"
				data-bind="textInput: employeeNumber"
			>
		</label>
		<label class="d-grid g8 fw600 mb22">
			<span>パスワード</span>
			<span class="d-grid gtc-1fr_auto g8">
				<input
					autocomplete="current-password"
					data-bind="textInput: password, attr: { type: passwordType }"
				>
				<button class="p8_16 c-navy ba-white bo1-border br6" type="button" data-bind="click: togglePassword, text: passwordToggleLabel"></button>
			</span>
		</label>
		<button class="w100p p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover" type="submit">ログイン</button>
	</form>
</section>
