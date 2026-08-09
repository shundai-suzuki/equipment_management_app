<section
	class="w520 p42 ba-surface bo1-border br10"
	data-page="login"
	data-submit-url="<?php echo e(Uri::create('login')); ?>"
	data-redirect-url="<?php echo e(Uri::create('dashboard')); ?>"
>
	<h1 class="m0 mb32 fs30 ta-center">ログイン</h1>
	<div
		class="mb22 p13_16 c-red ba-red_light bo1-border br6"
		role="alert"
		data-bind="visible: errorMessage, text: errorMessage"
	></div>
	<form data-auth-form data-bind="submit: submit">
		<?php echo Form::csrf(); ?>
		<label class="d-grid g8 fw600 mb22">
			<span>社員番号</span>
			<input
				type="text"
				inputmode="numeric"
				autocomplete="username"
				data-bind="textInput: employeeNumber, attr: { 'aria-invalid': fieldError('employee_number') ? 'true' : 'false' }"
			>
			<span class="c-red fs13" data-bind="visible: fieldError('employee_number'), text: fieldError('employee_number')"></span>
		</label>
		<label class="d-grid g8 fw600 mb22">
			<span>パスワード</span>
			<span class="d-grid gtc-1fr_auto g8">
				<input
					autocomplete="current-password"
					data-bind="textInput: password, attr: { type: passwordType, 'aria-invalid': fieldError('password') ? 'true' : 'false' }"
				>
				<button
					class="p8_16 c-navy ba-white bo1-border br6"
					type="button"
					data-bind="click: togglePassword, text: passwordToggleLabel"
				></button>
			</span>
			<span class="c-red fs13" data-bind="visible: fieldError('password'), text: fieldError('password')"></span>
		</label>
		<button
			class="w100p p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover cu-default-disabled o04-disabled"
			type="submit"
			data-bind="enable: ! isSubmitting()"
		>
			<span data-bind="text: isSubmitting() ? 'ログイン中...' : 'ログイン'"></span>
		</button>
	</form>
</section>
