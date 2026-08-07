<?php

/**
 * Common JSON API Controller.
 *
 * @package  app
 * @extends  Controller_Rest
 */
abstract class Controller_Base extends Controller_Rest
{
	const JSON_CONTENT_TYPE = 'application/json; charset=utf-8';

	/**
	 * Always return JSON without relying on an AJAX-only header.
	 *
	 * @var string
	 */
	protected $rest_format = 'json';

	/**
	 * true => need authentication, false => can be done without logging in.
	 *
	 * @var bool
	 */
	protected $authentication_required = true;

	/**
	 * Identifier shared by the response and server-side logs.
	 *
	 * @var string
	 */
	protected $request_id;

	/**
	 * Response prepared before an action when access must stop.
	 *
	 * @var Response|null
	 */
	protected $before_response;

	/**
	 * Initialize the request identifier and continued authentication.
	 *
	 * @return  void
	 */
	public function before()
	{
		parent::before();

		$this->request_id = bin2hex(random_bytes(16));

		if ($this->authentication_required and ! \Auth::check())
		{
			$this->before_response = $this->json_error(
				'AUTHENTICATION_REQUIRED',
				'ログインが必要です。',
				401
			);
		}
	}

	/**
	 * Stop dispatch when before() prepared an error response.
	 *
	 * @param   string  $resource
	 * @param   array   $arguments
	 * @return  Response|mixed
	 */
	public function router($resource, $arguments)
	{
		if ($this->before_response instanceof \Response)
		{
			return $this->before_response;
		}

		$response = parent::router($resource, $arguments);

		if ($response === null and $this->response->status === 405)
		{
			return $this->json_error(
				'METHOD_NOT_ALLOWED',
				'このHTTPメソッドは使用できません。',
				405
			);
		}

		return $response;
	}

	/**
	 * Build a successful response with optional list metadata.
	 *
	 * @param   array       $data
	 * @param   int         $status
	 * @param   array|null  $meta
	 * @return  Response
	 */
	protected function json_success(array $data, $status = 200, $meta = null)
	{
		$body = array('data' => $data);

		if ($meta !== null)
		{
			$body['meta'] = $meta;
		}

		$body['request_id'] = $this->request_id;

		return $this->json_response($body, $status);
	}

	/**
	 * Build an error response with optional field messages.
	 *
	 * @param   string      $code
	 * @param   string      $message
	 * @param   int         $status
	 * @param   array|null  $fields
	 * @return  Response
	 */
	protected function json_error($code, $message, $status, $fields = null)
	{
		$error = array(
			'code' => $code,
			'message' => $message,
		);

		if ($fields !== null)
		{
			$error['fields'] = $fields;
		}

		return $this->json_response(
			array(
				'error' => $error,
				'request_id' => $this->request_id,
			),
			$status
		);
	}

	/**
	 * Return the authenticated employee fields allowed in Controllers.
	 *
	 * @return  array|null
	 */
	protected function current_employee()
	{
		$driver = \Auth::instance();

		if ( ! $driver instanceof Auth_Login_Employee)
		{
			return null;
		}

		$id = $driver->get('id');
		$employee_name = $driver->get('employee_name');
		$role = $driver->get('role');

		if ( ! is_int($id)
			or ! is_string($employee_name)
			or ! in_array($role, array('EMPLOYEE', 'ADMIN'), true))
		{
			return null;
		}

		return array(
			'id' => $id,
			'employee_name' => $employee_name,
			'role' => $role,
		);
	}

	/**
	 * Encode the common body and attach the JSON content type.
	 *
	 * @param   array  $body
	 * @param   int    $status
	 * @return  Response
	 */
	protected function json_response(array $body, $status)
	{
		$json = \Format::forge($body)->to_json();

		if ( ! is_string($json))
		{
			throw new \RuntimeException('The JSON response could not be encoded.');
		}

		return \Response::forge(
			$json,
			$status,
			array('Content-Type' => static::JSON_CONTENT_TYPE)
		);
	}
}
