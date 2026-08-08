<?php

/**
 * Provides administrator checks and common CRUD steps for table Services.
 *
 * @package  app
 */
abstract class Service_BaseCrud extends Service_BaseRegistration
{
	const NOT_FOUND_EXCEPTION_CODE = 404;
	const FORBIDDEN_EXCEPTION_CODE = 403;
	const VALIDATION_EXCEPTION_CODE = 422;
	const CONFLICT_EXCEPTION_CODE = 409;
	const PER_PAGE = 10;
	const MAX_KEYWORD_LENGTH = 255;
	const MAX_SOFT_DELETE_REASON_LENGTH = 255;

	/**
	 * Employee Model for checking the administrator performing an operation.
	 *
	 * @var Model_Table_Employee|null
	 */
	protected $actor_model;

	/**
	 * Return one active row after rechecking the administrator.
	 *
	 * @param   int  $actor_id
	 * @param   int  $id
	 * @return  array
	 */
	public function read_for_admin($actor_id, $id)
	{
		$this->assert_admin_actor($actor_id);
		$this->assert_positive_id($id, 'The record ID');

		return $this->read_required_record($id);
	}

	/**
	 * Return an active-row list with validated pagination.
	 *
	 * @param   int     $actor_id
	 * @param   int     $page
	 * @param   string  $keyword
	 * @param   array   $filters
	 * @return  array
	 */
	public function search_for_admin($actor_id, $page, $keyword = '', array $filters = array())
	{
		$this->assert_admin_actor($actor_id);

		if ( ! is_int($page) or $page < 1)
		{
			throw new \InvalidArgumentException('The page must be a positive integer.');
		}

		if ( ! is_string($keyword))
		{
			throw new \InvalidArgumentException('The keyword must be a string.');
		}

		$keyword = trim($keyword);

		if (mb_strlen($keyword, 'UTF-8') > static::MAX_KEYWORD_LENGTH)
		{
			throw new \InvalidArgumentException('The keyword is too long.');
		}

		return $this->model->search($page, self::PER_PAGE, $keyword, $filters);
	}

	/**
	 * Require an active row.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array
	 */
	protected function read_required_record($id, $db = null)
	{
		$read_record = $this->model->read($id, false, $db);

		if ($read_record === null)
		{
			throw new \RuntimeException(
				'The requested record was not found.',
				static::NOT_FOUND_EXCEPTION_CODE
			);
		}

		return $read_record;
	}

	/**
	 * Update an already locked row and return its current representation.
	 *
	 * @param   int                       $id
	 * @param   array                     $update_values
	 * @param   Database_Connection|null  $db
	 * @return  array
	 */
	protected function update_and_read_record($id, array $update_values, $db = null)
	{
		$this->model->update($id, $update_values, $db);
		$updated_record = $this->model->read($id, false, $db);

		if ($updated_record === null)
		{
			throw new \RuntimeException(
				'The record changed during the update.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		return $updated_record;
	}

	/**
	 * Soft-delete an already locked row and return its current representation.
	 *
	 * @param   int                       $id
	 * @param   Database_Connection|null  $db
	 * @return  array
	 */
	protected function soft_delete_and_read_record($id, $db = null)
	{
		if ($this->model->soft_delete($id, $db) !== 1)
		{
			throw new \RuntimeException(
				'The record changed during the soft-delete operation.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		$soft_deleted_record = $this->model->read($id, true, $db);

		if ($soft_deleted_record === null)
		{
			throw new \RuntimeException(
				'The soft-deleted record could not be read.',
				static::CONFLICT_EXCEPTION_CODE
			);
		}

		return $soft_deleted_record;
	}

	/**
	 * Require a reason suitable for the later audit event.
	 *
	 * @param   mixed  $reason
	 * @return  void
	 */
	protected function assert_soft_delete_reason($reason)
	{
		if ( ! is_string($reason))
		{
			throw new \InvalidArgumentException('The soft-delete reason must be a string.');
		}

		$reason = trim($reason);

		if ($reason === ''
			or mb_strlen($reason, 'UTF-8') > static::MAX_SOFT_DELETE_REASON_LENGTH)
		{
			throw new \InvalidArgumentException('The soft-delete reason is invalid.');
		}
	}

	/**
	 * Recheck that the actor remains an active administrator.
	 *
	 * @param   int                       $actor_id
	 * @param   Database_Connection|null  $db
	 * @return  void
	 */
	protected function assert_admin_actor($actor_id, $db = null)
	{
		$this->assert_positive_id($actor_id, 'The administrator employee ID');

		if ($this->actor_model === null)
		{
			$this->actor_model = new Model_Table_Employee();
		}

		if ( ! $this->actor_model->is_active_admin($actor_id, $db))
		{
			throw new \RuntimeException(
				'The actor is not an active administrator.',
				static::FORBIDDEN_EXCEPTION_CODE
			);
		}
	}
}
