<section class="maw900 m-0_auto" data-page="password">
	<header class="d-flex ai-center jc-space_between mb24">
		<h1 class="m0 fs30">パスワード変更</h1>
	</header>
	<div class="p32 ba-surface bo1-border br10">
		<form data-bind="submit: submit">
			<?php echo Form::csrf(); ?>
			<!-- ko foreach: fields -->
			<label class="d-grid g8 fw600 mb22">
				<span data-bind="text: label"></span>
				<span class="d-grid gtc-1fr_auto g8">
					<input
						autocomplete="off"
						data-bind="textInput: value, attr: { type: $root.passwordType }"
					>
				</span>
			</label>
			<!-- /ko -->
			<p class="mb22 c-muted fs13">新しいパスワードは12バイト以上72バイト以下で入力してください。</p>
			<div class="d-flex jc-flex_end g12">
				<button class="p10_18 fw700 br6 c-green ba-white bo1-green" type="button" data-bind="click: togglePassword, text: passwordToggleLabel"></button>
				<button class="p10_18 fw700 br6 c-white ba-green bo1-green ba-green_dark-hover" type="submit">変更する</button>
			</div>
		</form>
	</div>
</section>
