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

	public function in_transaction()
	{
		return $this->transaction;
	}

	public function acquire_lock($lock_name, $timeout_seconds)
	{
		$this->acquire_count++;

		return true;
	}

	public function release_lock($lock_name)
	{
		$this->release_count++;

		return true;
	}

	public function start_transaction()
	{
		$this->transaction = true;

		return true;
	}

	public function commit_transaction()
	{
		$this->commit_count++;
		$this->transaction = false;

		return true;
	}

	public function rollback_transaction()
	{
		$this->rollback_count++;
		$this->transaction = false;

		return true;
	}

	public function max_id($table)
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

		call_user_func($operation, $id, 'test-connection');
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
		$model = new Tests_IdAllocator_Model();
		$service = new Service_IdAllocator($model);
		$id = $service->allocate(
			'departments',
			function ($allocated_id, $connection) use ($model)
			{
				$this->assertSame(42, $allocated_id);
				$this->assertSame('test-connection', $connection);
				$model->inserted = true;
			}
		);

		$this->assertSame(42, $id);
		$this->assertSame(1, $model->commit_count);
		$this->assertSame(1, $model->release_count);
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
		$model = new Tests_IdAllocator_Model();
		$model->max_id = Service_IdAllocator::MAX_ID;
		$service = new Service_IdAllocator($model);

		try
		{
			$service->allocate('employees', function () {});
			$this->fail('The signed INT upper bound was accepted.');
		}
		catch (\OverflowException $e)
		{
			$this->assertSame(1, $model->rollback_count);
			$this->assertSame(1, $model->release_count);
		}
	}

	/**
	 * @test
	 */
	public function test_service_retries_a_duplicate_allocated_id()
	{
		$model = new Tests_IdAllocator_Model();
		$model->duplicate_once = true;
		$service = new Service_IdAllocator($model);
		$id = $service->allocate(
			'loans',
			function ($allocated_id) use ($model)
			{
				$model->inserted = true;
			}
		);

		$this->assertSame(42, $id);
		$this->assertSame(2, $model->acquire_count);
		$this->assertSame(1, $model->rollback_count);
		$this->assertSame(2, $model->release_count);
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
