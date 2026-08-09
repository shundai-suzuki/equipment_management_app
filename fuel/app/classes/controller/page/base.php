<?php

/**
 * HTMLアプリケーション画面の共通コントローラ。
 *
 * @package  app
 * @extends  Controller_Template
 */
abstract class Controller_Page_Base extends Controller_Template
{
	public $template = 'layouts/application';

	/**
	 * 認証なしで表示できるアクション。
	 *
	 * @var array
	 */
	protected $guest_actions = array();

	/**
	 * 認証済み社員が管理者かどうか。
	 *
	 * @var bool
	 */
	protected $is_admin = false;

	/**
	 * HTML画面のアクション実行前に認証する。
	 *
	 * @return  void
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
	 * 認証済み画面または未認証用ログイン画面を1つ表示する。
	 *
	 * @param   string  $view
	 * @param   string  $title
	 * @param   string  $active_page
	 * @param   string  $page_script
	 * @param   array   $view_data
	 * @param   bool    $guest
	 * @return  void
	 */
	protected function render_page($view, $title, $active_page, $page_script, array $view_data = array(), $guest = false)
	{
		if ($guest)
		{
			$this->template = \View::forge('layouts/guest');
		}

		$this->template->title = $title;
		$this->template->active_page = $active_page;
		$this->template->page_script = $page_script;
		$this->template->is_admin = $this->is_admin;
		$view_data['is_admin'] = $this->is_admin;
		$this->template->content = \View::forge($view, $view_data);
	}

	/**
	 * 現在の権限を返し、未認証リクエストはログイン画面へ移動する。
	 *
	 * @return  string
	 */
	protected function authenticated_role()
	{
		if ( ! \Auth::check())
		{
			\Response::redirect('login');
		}

		$driver = \Auth::instance();
		$role = $driver instanceof Auth_Login_Employee
			? $driver->get_role()
			: false;

		if ( ! in_array($role, array('EMPLOYEE', 'ADMIN'), true))
		{
			\Response::redirect('login');
		}

		return $role;
	}

	/**
	 * API連携された共通一覧・フォーム画面を表示する。
	 *
	 * @param   string  $resource
	 * @param   string  $title
	 * @return  void
	 */
	protected function render_resource($resource, $title)
	{
		$search_paths = array(
			'equipment' => 'api/equipment',
			'loans' => 'api/loans',
			'employees' => 'api/admin/employees',
			'departments' => 'api/admin/departments',
		);
		$write_paths = array(
			'equipment' => 'api/admin/equipment',
			'loans' => 'api/admin/loans',
			'employees' => 'api/admin/employees',
			'departments' => 'api/admin/departments',
		);

		if ( ! isset($search_paths[$resource], $write_paths[$resource]))
		{
			throw new \LogicException('The page resource is not configured.');
		}

		$this->render_page(
			'resource',
			$title,
			$resource,
			'app/resource.js',
			array(
				'resource' => $resource,
				'search_url' => \Uri::create($search_paths[$resource]),
				'write_url' => $this->is_admin
					? \Uri::create($write_paths[$resource])
					: '',
				'departments_url' => \Uri::create('api/departments'),
				'login_url' => \Uri::create('login'),
			)
		);
	}
}
