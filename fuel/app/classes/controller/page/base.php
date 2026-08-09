<?php

/**
 * Common Controller for HTML application pages.
 *
 * @package  app
 * @extends  Controller_Template
 */
abstract class Controller_Page_Base extends Controller_Template
{
	public $template = 'layouts/application';

	/**
	 * Actions that may render without authentication.
	 *
	 * @var array
	 */
	protected $guest_actions = array();

	/**
	 * Whether the authenticated employee is an administrator.
	 *
	 * @var bool
	 */
	protected $is_admin = false;

	/**
	 * Authenticate HTML pages before their action runs.
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
	 * Render one authenticated page or the guest login page.
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
	 * Return the current role or redirect an unauthenticated request.
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
	 * Render the shared API-backed list and form page.
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
