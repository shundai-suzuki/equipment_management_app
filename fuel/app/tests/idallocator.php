<?php

/**
 * Controllable Model double for ID allocator tests.
 */
class Tests_IdAllocator_Model
{
	public $transaction = false;
	public $max_id = 41;
	public $inserted = false;
	public $duplicate_once = false;
	public $execute_count = 0;
	public $acquire_count = 0;
	public $release_count = 0;
	public $rollback_count = 0;
	public $commit_count = 0;
	public $disconnect_count = 0;
	public $acquire_result = true;
	public $start_result = true;
	public $commit_result = true;
	public $rollback_result = true;
	public $disconnect_result = true;

	public function in_transaction()
	{
		return $this->transaction;
	}

	public function acquire_lock($lock_name, $timeout_seconds)
	{
		$this->acquire_count++;

		return $this->acquire_result;
	}

	public function release_lock($lock_name)
	{
		$this->release_count++;

		return true;
	}

	public function start_transaction()
	{
		if ($this->start_result)
		{
			$this->transaction = true;
		}

		return $this->start_result;
	}

	public function commit_transaction()
	{
		$this->commit_count++;

		if ($this->commit_result)
		{
			$this->transaction = false;
		}

		return $this->commit_result;
	}

	public function rollback_transaction()
	{
		$this->rollback_count++;

		if ($this->rollback_result)
		{
			$this->transaction = false;
		}

		return $this->rollback_result;
	}

	public function disconnect()
	{
		$this->disconnect_count++;

		if ($this->disconnect_result)
		{
			$this->transaction = false;
		}

		return $this->disconnect_result;
	}

	public function get_max_id($table)
	{
		return $this->max_id;
	}

	public function execute_insert(\Closure $operation, $id)
	{
		$this->execute_count++;

		if ($this->duplicate_once and $this->execute_count === 1)
		{
			$this->inserted = true;
			throw new \Database_Exception('duplicate', 1062);
		}

		$operation($id, 'test-connection');
	}

	public function id_exists($table, $id)
	{
		return $this->inserted;
	}
}

/**
 * Service double used to verify Controller delegation.
 */
class Tests_IdAllocator_Service
{
	public $called = false;

	public function allocate($table, \Closure $operation)
	{
		$this->called = true;

		return 77;
	}
}

/**
 * Concrete test Controller that exposes the protected allocation method.
 */
class Tests_IdAllocator_Controller extends Controller_IdAllocator
{
	public function __construct() {}

	public function set_service($service)
	{
		$this->id_allocator_service = $service;
	}

	public function allocate_for_test($table, \Closure $operation)
	{
		return $this->allocate_id($table, $operation);
	}
}

/**
 * ID allocator layer tests.
 *
 * @group App
 * @group IdAllocator
 */
class Tests_IdAllocator extends \Fuel\Core\TestCase
{
	/**
	 * @test
	 */
	public function test_service_calculates_next_id_and_commits()
	{
		$test_model = new Tests_IdAllocator_Model();
		$service = new Service_IdAllocator($test_model);
		$id = $service->allocate(
			'departments',
			function ($allocated_id, $connection) use ($test_model)
			{
				$this->assertSame(42, $allocated_id);
				$this->assertSame('test-connection', $connection);
				$test_model->inserted = true;
			}
		);

		$this->assertSame(42, $id);
		$this->assertSame(1, $test_model->commit_count);
		$this->assertSame(1, $test_model->release_count);
	}

	/**
	 * @test
	 */
	public function test_service_rejects_an_unsupported_table()
	{
		$service = new Service_IdAllocator(new Tests_IdAllocator_Model());

		try
		{
			$service->allocate('unsupported', function () {});
			$this->fail('An unsupported table was accepted.');
		}
		catch (\InvalidArgumentException $e)
		{
			$this->assertSame('Unsupported ID allocation table.', $e->getMessage());
		}
	}

	/**
	 * @test
	 */
	public function test_service_rolls_back_when_the_id_limit_is_reached()
	{
		$test_model = new Tests_IdAllocator_Model();
		$test_model->max_id = Service_IdAllocator::MAX_ID;
		$service = new Service_IdAllocator($test_model);

		try
		{
			$service->allocate('employees', function () {});
			$this->fail('The signed INT upper bound was accepted.');
		}
		catch (\OverflowException $e)
		{
			$this->assertSame(1, $test_model->rollback_count);
			$this->assertSame(1, $test_model->release_count);
		}
	}

	/**
	 * @test
	 */
	public function test_service_retries_a_duplicate_allocated_id()
	{
		$test_model = new Tests_IdAllocator_Model();
		$test_model->duplicate_once = true;
		$service = new Service_IdAllocator($test_model);
		$id = $service->allocate(
			'loans',
			function () use ($test_model)
			{
				$test_model->inserted = true;
			}
		);

		$this->assertSame(42, $id);
		$this->assertSame(2, $test_model->acquire_count);
		$this->assertSame(1, $test_model->rollback_count);
		$this->assertSame(2, $test_model->release_count);
	}

	/**
	 * @test
	 */
	public function test_service_does_not_release_an_unacquired_lock()
	{
		$test_model = new Tests_IdAllocator_Model();
		$test_model->acquire_result = false;
		$service = new Service_IdAllocator($test_model);

		try
		{
			$service->allocate('departments', function () {});
			$this->fail('A failed lock acquisition was accepted.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame(Service_IdAllocator::CONFLICT_EXCEPTION_CODE, $e->getCode());
			$this->assertSame(0, $test_model->release_count);
			$this->assertSame(0, $test_model->rollback_count);
		}
	}

	/**
	 * @test
	 */
	public function test_service_releases_the_lock_when_transaction_start_fails()
	{
		$test_model = new Tests_IdAllocator_Model();
		$test_model->start_result = false;
		$service = new Service_IdAllocator($test_model);

		try
		{
			$service->allocate('departments', function () {});
			$this->fail('A failed transaction start was accepted.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame('Failed to start the ID allocation transaction.', $e->getMessage());
			$this->assertSame(1, $test_model->release_count);
			$this->assertSame(0, $test_model->rollback_count);
		}
	}

	/**
	 * @test
	 */
	public function test_service_rolls_back_when_the_insert_does_not_create_the_id()
	{
		$test_model = new Tests_IdAllocator_Model();
		$service = new Service_IdAllocator($test_model);

		try
		{
			$service->allocate('departments', function () {});
			$this->fail('An insert without the allocated ID was accepted.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame(
				'The ID allocation operation did not insert the allocated ID.',
				$e->getMessage()
			);
			$this->assertSame(1, $test_model->rollback_count);
			$this->assertSame(1, $test_model->release_count);
		}
	}

	/**
	 * @test
	 */
	public function test_service_discards_the_connection_when_commit_fails()
	{
		$test_model = new Tests_IdAllocator_Model();
		$test_model->commit_result = false;
		$service = new Service_IdAllocator($test_model);

		try
		{
			$service->allocate(
				'departments',
				function () use ($test_model)
				{
					$test_model->inserted = true;
				}
			);
			$this->fail('A failed commit was accepted.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame('Failed to commit the ID allocation transaction.', $e->getMessage());
			$this->assertSame(1, $test_model->commit_count);
			$this->assertSame(0, $test_model->rollback_count);
			$this->assertSame(0, $test_model->release_count);
			$this->assertSame(1, $test_model->disconnect_count);
		}
	}

	/**
	 * @test
	 */
	public function test_service_discards_the_connection_when_rollback_fails()
	{
		$test_model = new Tests_IdAllocator_Model();
		$test_model->max_id = Service_IdAllocator::MAX_ID;
		$test_model->rollback_result = false;
		$service = new Service_IdAllocator($test_model);

		try
		{
			$service->allocate('departments', function () {});
			$this->fail('A failed rollback was accepted.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame('Failed to roll back the ID allocation transaction.', $e->getMessage());
			$this->assertSame(1, $test_model->rollback_count);
			$this->assertSame(0, $test_model->release_count);
			$this->assertSame(1, $test_model->disconnect_count);
		}
	}

	/**
	 * @test
	 */
	public function test_service_does_not_release_the_lock_when_disconnect_fails()
	{
		$test_model = new Tests_IdAllocator_Model();
		$test_model->max_id = Service_IdAllocator::MAX_ID;
		$test_model->rollback_result = false;
		$test_model->disconnect_result = false;
		$service = new Service_IdAllocator($test_model);

		try
		{
			$service->allocate('departments', function () {});
			$this->fail('A failed connection discard was accepted.');
		}
		catch (\RuntimeException $e)
		{
			$this->assertSame(
				'Failed to discard the ID allocation database connection.',
				$e->getMessage()
			);
			$this->assertSame(1, $test_model->rollback_count);
			$this->assertSame(0, $test_model->release_count);
			$this->assertSame(1, $test_model->disconnect_count);
		}
	}

	/**
	 * @test
	 */
	public function test_controller_delegates_to_the_service()
	{
		$service = new Tests_IdAllocator_Service();
		$controller = new Tests_IdAllocator_Controller();
		$controller->set_service($service);

		$id = $controller->allocate_for_test('equipments', function () {});

		$this->assertSame(77, $id);
		$this->assertTrue($service->called);
	}
}
