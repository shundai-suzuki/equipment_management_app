<?php

/**
 * Common Controller for HTML prototype pages.
 *
 * @package  app
 * @extends  Controller_Template
 */
abstract class Controller_Page_Base extends Controller_Template
{
	public $template = 'layouts/application';

	/**
	 * Render one page without loading business data.
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
		$this->template->content = \View::forge($view, $view_data);
	}

	/**
	 * Render the shared list and form page.
	 *
	 * @param   string  $resource
	 * @param   string  $title
	 * @return  void
	 */
	protected function render_resource($resource, $title)
	{
		$this->render_page(
			'resource',
			$title,
			$resource,
			'app/resource.js',
			array('resource' => $resource)
		);
	}
}
