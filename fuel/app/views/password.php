<section
	class="maw900 m-0_auto"
	data-page="password"
	data-submit-url="<?php echo e(Uri::create('account/password')); ?>"
	data-login-url="<?php echo e(Uri::create('login')); ?>"
>
	<header class="d-flex ai-center jc-space_between mb24">
		<h1 class="m0 fs30">パスワード変更</h1>
	</header>
	<div class="p32 ba-surface bo1-border br10">
		<div
			class="mb22 p13_16 c-red ba-red_light bo1-border br6"
			role="alert"
			data-bind="visible: errorMessage, text: errorMessage"
		></div>
		<div
			class="mb22 p13_16 c-green ba-green_light bo1-border br6"
			role="status"
			data-bind="visible: successMessage, text: successMessage"
		></div>
		<form data-auth-form data-bind="submit: submit">
			<?php echo Form::csrf(); ?>
			<!-- ko foreach: fields -->
			<label class="d-grid g8 fw600 mb22">
				<span data-bind="text: label"></span>
				<span class="d-grid gtc-1fr_auto g8">
					<input
						data-bind="textInput: value, attr: { type: $root.passwordType, autocomplete: autocomplete, 'aria-invalid': $root.fieldError(key) ? 'true' : 'false' }"
					>
				</span>
				<span class="c-red fs13" data-bind="visible: $root.fieldError(key), text: $root.fieldError(key)"></span>
			</label>
			<!-- /ko -->
			<p class="mb22 c-muted fs13">新しいパスワードは12バイト以上72バイト以下で入力してください。</p>
			<div class="d-flex jc-flex_end g12">
				<button class="p10_18 fw700 br6 c-green ba-white bo1-green" type="button" data-bind="click: togglePassword, text: passwordToggleLabel"></button>
				<button
					class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover cu-default-disabled o04-disabled"
					type="submit"
					data-bind="enable: ! isSubmitting(), text: isSubmitting() ? '変更中...' : '変更する'"
				></button>
			</div>
		</form>
	</div>
</section>
