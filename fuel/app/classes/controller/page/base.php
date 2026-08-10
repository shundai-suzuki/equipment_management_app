<?php

/**
 * HTML画面、認証確認、通常フォーム処理の共通コントローラ。
 */
abstract class Controller_Page_Base extends \Controller_Template
{
	/** @var string ログイン後の共通レイアウト。 */
	public $template = 'layouts/application';

	/** @var array 認証なしで実行できるアクション名。 */
	protected $guest_actions = array();

	/** @var bool 現在の社員が管理者かどうか。 */
	protected $is_admin = false;

	/**
	 * 画面表示前に必要な認証と権限を確認する。
	 *
	 * @return void 
	 */
	public function before()
	{
		parent::before();

		if ( ! in_array($this->request->action, $this->guest_actions, true))
		{
			$this->is_admin = $this->authenticated_role() === 'ADMIN';
		}
	}
	/**
	 * 指定Viewを共通レイアウトへ設定する。
	 *
	 * @param string 	$view 				表示するView名
	 * @param string 	$title 				画面タイトル
	 * @param string 	$active_page 	現在の画面を示す名前
	 * @param string 	$page_script 	読み込むJavaScriptファイル
	 * @param array 	$data 				Viewへ渡すデータ
	 * @param bool 		$guest 				未認証用レイアウトを使用するか
	 * @return void 
	 */
	protected function render_page($view, $title, $active_page, $page_script, array $data = array(), $guest = false)
	{
		if ($guest)
		{
			$this->template = \View::forge('layouts/guest');
		}

		$data['is_admin'] = $this->is_admin;
		$this->template->title = $title;
		$this->template->active_page = $active_page;
		$this->template->page_script = $page_script;
		$this->template->is_admin = $this->is_admin;
		$this->template->notice = \Session::get_flash('notice', '');
		$this->template->error = \Session::get_flash('error', '');
		$this->template->content = \View::forge($view, $data);
	}

	/**
	 * 検索APIと通常POST先を持つ共通一覧画面を表示する。
	 *
	 * @param string $resource 表示対象のリソース名。
	 * @param string $title 画面タイトル。
	 * @return void 
	 */
	protected function render_resource($resource, $title)
	{
		$search = array(
			'equipment' => 'api/equipment',
			'loans' => 'api/loans',
			'employees' => 'api/admin/employees',
			'departments' => 'api/admin/departments',
		);

		if ( ! isset($search[$resource]))
		{
			throw new \LogicException('The page resource is not configured.');
		}

		$this->render_page('resource', $title, $resource, 'app/resource.js', array(
			'resource' => $resource,
			'search_url' => \Uri::create($search[$resource]),
			'write_url' => $this->is_admin ? \Uri::create('admin/'.$resource) : '',
			'departments_url' => \Uri::create('api/departments'),
			'login_url' => \Uri::create('login'),
		));
	}

	/**
	 * 認証済み社員の権限を返す。
	 *
	 * @return string 認証済み社員の権限。
	 */
	protected function authenticated_role()
	{
		if ( ! \Auth::check())
		{
			\Response::redirect('login');
		}

		$driver = \Auth::instance();
		$role = $driver instanceof Auth_Login_Employee ? $driver->get_role() : false;

		if ( ! in_array($role, array('EMPLOYEE', 'ADMIN'), true))
		{
			\Auth::logout();
			\Response::redirect('login');
		}

		return $role;
	}

	/**
	 * 認証済み社員のIDを返す。
	 *
	 * @return int 認証済み社員のID。
	 */
	protected function employee_id()
	{
		$driver = \Auth::instance();
		$id = $driver instanceof Auth_Login_Employee ? $driver->get('id') : null;

		if ( ! is_int($id) or $id < 1)
		{
			throw new \RuntimeException('The authenticated employee is invalid.', 403);
		}

		return $id;
	}

	/**
	 * POSTされた正の整数を取得する。
	 *
	 * @param string $name POST項目名。
	 * @return int 取得した正の整数。
	 */
	protected function post_integer($name)
	{
		return $this->positive_integer(\Input::post($name), $name);
	}

	/**
	 * 入力値を正の整数へ変換する。
	 *
	 * @param int|string $value 変換する入力値。
	 * @param string $name 入力項目名。
	 * @return int 変換した正の整数。
	 */
	protected function positive_integer($value, $name)
	{
		if (is_string($value) and preg_match('/\A[1-9][0-9]*\z/', $value))
		{
			$value = (int) $value;
		}

		if ( ! is_int($value) or $value < 1 or $value > 2147483647)
		{
			throw new \InvalidArgumentException($name.' must be a positive integer.');
		}

		return $value;
	}

	/**
	 * フォーム処理結果をフラッシュへ保存してリダイレクトする。
	 *
	 * @param \Closure $operation 実行するフォーム処理。
	 * @param string $redirect 処理後のリダイレクト先。
	 * @return void 
	 */
	protected function form_result(\Closure $operation, $redirect)
	{
		try
		{
			$operation();
			\Session::set_flash('notice', '処理が完了しました。');
		}
		catch (\InvalidArgumentException $exception)
		{
			\Session::set_flash('error', '入力内容を確認してください。');
		}
		catch (\Database_Exception $exception)
		{
			\Session::set_flash(
				'error',
				(int) $exception->getCode() === 1062
					? '同じ内容のデータが既に存在します。'
					: '処理に失敗しました。'
			);
		}
		catch (\RuntimeException $exception)
		{
			$messages = array(
				403 => 'この操作を行う権限がありません。',
				404 => '対象のデータが見つかりません。',
				409 => '現在のデータ状態では処理できません。',
				422 => '入力内容を確認してください。',
			);
			\Session::set_flash('error', isset($messages[$exception->getCode()])
				? $messages[$exception->getCode()]
				: '処理に失敗しました。');
		}
		catch (\Throwable $exception)
		{
			try
			{
				\Log::error('HTML form failure: '.get_class($exception));
			}
			catch (\Throwable $ignored)
			{
			}
			\Session::set_flash('error', '処理に失敗しました。');
		}

		\Response::redirect($redirect);
	}
}
