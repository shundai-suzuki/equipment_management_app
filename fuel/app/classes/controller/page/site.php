<?php

/**
 * すべてのアプリケーション権限で利用できるHTML画面。
 *
 * @package  app
 * @extends  Controller_Page_Base
 */
class Controller_Page_Site extends Controller_Page_Base
{
	/**
	 * ログインだけを認証なしで利用できる画面とする。
	 *
	 * @var array
	 */
	protected $guest_actions = array('login');

	public function action_login()
	{
		if (\Auth::check())
		{
			\Response::redirect('dashboard');
		}

		$this->render_page(
			'login',
			'ログイン',
			'',
			'app/auth.js',
			array(),
			true
		);
	}

	public function action_password()
	{
		$this->render_page(
			'password',
			'パスワード変更',
			'password',
			'app/auth.js'
		);
	}

	public function action_dashboard()
	{
		$this->render_page(
			'dashboard',
			'ダッシュボード',
			'dashboard',
			'app/dashboard.js'
		);
	}

	public function action_equipment()
	{
		$this->render_resource('equipment', '備品一覧');
	}

	public function action_loans()
	{
		$this->render_resource('loans', '貸出一覧');
	}
}
