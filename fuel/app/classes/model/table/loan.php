<?php

/**
 * Provides database operations for loans.
 *
 * @package  app
 */
class Model_Table_Loan extends Model_BaseCrud
{
	/**
	 * Table operated by this Model.
	 *
	 * @var string
	 */
	protected static $table_name = 'loans';

	/**
	 * Loan create values accepted for a new row.
	 *
	 * @var array
	 */
	protected static $create_columns = array(
		'employee_id',
		'equipment_id',
		'due_date',
		'loaned_at',
		'loaned_by',
		'returned_at',
		'returned_by',
		'note',
	);
}
