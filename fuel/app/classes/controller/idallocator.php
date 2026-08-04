<?php

/**
 * Base Controller for create actions that need application-managed IDs.
 *
 * This Controller does not expose an ID allocation action. Concrete create
 * Controllers call allocate_id(), which delegates only to the Service layer.
 *
 * @package  app
 * @extends  Controller
 */
abstract class Controller_IdAllocator extends Controller
{
	/**
	 * @var Service_IdAllocator
	 */
	protected $id_allocator_service;

	/**
	 * Prepare the Service dependency before a create action is dispatched.
	 *
	 * @return  void
	 */
	public function before()
	{
		parent::before();

		$this->id_allocator_service = new Service_IdAllocator();
	}

	/**
	 * Delegate ID allocation to the Service layer.
	 *
	 * @param   string   $table
	 * @param   Closure  $operation
	 * @return  int
	 */
	protected function allocate_id($table, \Closure $operation)
	{
		if ($this->id_allocator_service === null)
		{
			throw new \LogicException('The ID allocator service has not been initialized.');
		}

		return $this->id_allocator_service->allocate($table, $operation);
	}
}
