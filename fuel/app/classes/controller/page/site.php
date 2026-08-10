<?php

/**
 * 全社員が利用するHTML画面と認証フォーム。
 */
class Controller_Page_Site extends Controller_Page_Base
{
	/** @var array ログイン前に利用できるアクション名 */
	protected $guest_actions = array('login', 'login_submit');

	/**
	 * ログイン画面を表示する。
	 *
	 * @return void
	 */
	public function get_login()
	{
		if (\Auth::check())
		{
			\Response::redirect('dashboard');
		}

		$this->render_page('login', 'ログイン', '', '', array(), true);
	}

	/**
	 * ログインフォームを認証し、結果に応じてリダイレクトする。
	 *
	 * @return void
	 */
	public function post_login_submit()
	{
		$employee_number = \Input::post('employee_number', '');
		$password = \Input::post('password', '');
		$ip = \Input::server('REMOTE_ADDR');

		try
		{
			$limit = new Security_LoginRateLimit();

			if ($limit->is_blocked($employee_number, $ip))
			{
				throw new \RuntimeException('Rate limited.');
			}

			if ( ! \Auth::login($employee_number, $password))
			{
				$limit->record_failure($employee_number, $ip);
				throw new \InvalidArgumentException('Authentication failed.');
			}

			$limit->record_success($employee_number);

			\Response::redirect('dashboard');
		}
		catch (\Throwable $exception)
		{
			if (\Auth::check())
			{
				\Auth::logout();
			}

			\Session::set_flash('error', '社員番号またはパスワードを確認してください。');

			\Response::redirect('login');
		}
	}

	/**
	 * 認証Sessionを破棄してログイン画面へ戻す。
	 *
	 * @return void
	 */
	public function post_logout()
	{
		\Auth::logout();

		\Response::redirect('login');
	}

	/**
	 * 本人用パスワード変更画面を表示する。
	 *
	 * @return void
	 */
	public function get_password()
	{
		$this->render_page('password', 'パスワード変更', 'password', '');
	}

	/**
	 * 本人のパスワードを変更してSessionを再生成する。
	 *
	 * @return void
	 */
	public function post_password_submit()
	{
		$this->form_result(function ()
		{
			$employee_id = $this->employee_id();
			$password = \Input::post('password');
			(new Service_Table_Employee())->change_own_password(
				$employee_id,
				\Input::post('current_password'),
				$password,
				\Input::post('password_confirmation')
			);

			if ( ! \Auth::login($employee_id, $password))
			{
				\Auth::logout();
				throw new \RuntimeException('Session regeneration failed.');
			}
		}, 'account/password');
	}

	/**
	 * ダッシュボードを表示する。
	 *
	 * @return void
	 */
	public function get_dashboard()
	{
		$search_result = (new Service_Table_Loan())->search_for_actor(
			$this->employee_id(),	1, '', array('active_only' => true)
		);

		$this->render_page(
			'dashboard', 'ダッシュボード', 'dashboard', '', array('loans' => array_slice($search_result['rows'], 0, 6))
		);
	}

	/**
	 * 備品一覧を表示する。
	 *
	 * @return void
	 */
	public function get_equipment()
	{
		$this->render_resource('equipment', '備品一覧');
	}

	/**
	 * 権限に応じた貸出一覧を表示する。
	 *
	 * @return void
	 */
	public function get_loans()
	{
		$this->render_resource('loans', '貸出一覧');
	}
}
