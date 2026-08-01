<?php

namespace Fuel\Migrations;

class Create_loans
{
	public function up()
	{
		\DBUtil::create_table(
			'loans', 
			array(
				'id' => array('type' => 'int', 'constraint' => 11, 'default' => 0),
				'employee_id' => array('type' => 'int', 'constraint' => 11, 'default' => 0),
				'equipment_id' => array('type' => 'int', 'constraint' => 11, 'default' => 0),
				'due_date' => array('type' => 'date'),
				'loaned_at' => array('type' => 'date'),
				'loaned_by' => array('type' => 'int', 'constraint' => 11, 'default' => 0),
				'returned_at' => array('type' => 'date', 'null' => true),
				'returned_by' => array('type' => 'int', 'constraint' => 11, 'null' => true),
				'note' => array('type' => 'varchar', 'constraint' => 255, 'null' => true),
				'created_at' => array('type' => 'timestamp', 'null' => true),
				'updated_at' => array('type' => 'timestamp', 'null' => true),
		), array('id'), false, 'InnoDB', 'utf8mb4');

		\DBUtil::create_index(
			'loans',
			array('employee_id', 'returned_at', 'created_at'),
			'idx_loans_employee_returned_created'
		);
		\DBUtil::create_index(
			'loans',
			array('equipment_id', 'returned_at'),
			'idx_loans_equipment_returned'
		);
		\DBUtil::create_index('loans', array('loaned_by'), 'idx_loans_loaned_by');
		\DBUtil::create_index('loans', array('returned_by'), 'idx_loans_returned_by');
		\DBUtil::create_index(
			'loans',
			array('returned_at', 'due_date'),
			'idx_loans_returned_due'
		);

		\DBUtil::add_foreign_key('loans', array(
			'constraint' => 'fk_loans_employee',
			'key' => 'employee_id',
			'reference' => array(
				'table' => 'employees',
				'column' => 'id',
			),
			'on_update' => 'RESTRICT',
			'on_delete' => 'RESTRICT',
		));
		\DBUtil::add_foreign_key('loans', array(
			'constraint' => 'fk_loans_equipment',
			'key' => 'equipment_id',
			'reference' => array(
				'table' => 'equipments',
				'column' => 'id',
			),
			'on_update' => 'RESTRICT',
			'on_delete' => 'RESTRICT',
		));
		\DBUtil::add_foreign_key('loans', array(
			'constraint' => 'fk_loans_loaned_by',
			'key' => 'loaned_by',
			'reference' => array(
				'table' => 'employees',
				'column' => 'id',
			),
			'on_update' => 'RESTRICT',
			'on_delete' => 'RESTRICT',
		));
		\DBUtil::add_foreign_key('loans', array(
			'constraint' => 'fk_loans_returned_by',
			'key' => 'returned_by',
			'reference' => array(
				'table' => 'employees',
				'column' => 'id',
			),
			'on_update' => 'RESTRICT',
			'on_delete' => 'RESTRICT',
		));

		$table = \DB::quote_identifier(\DB::table_prefix('loans'));
		\DB::query(
			'ALTER TABLE '.$table
			.' ADD CONSTRAINT chk_loans_id_positive CHECK (id > 0),'
			.' ADD CONSTRAINT chk_loans_return_pair CHECK ('
			.'(returned_at IS NULL AND returned_by IS NULL) '
			.'OR (returned_at IS NOT NULL AND returned_by IS NOT NULL)'
			.'),'
			.' ADD CONSTRAINT chk_loans_return_date CHECK ('
			.'returned_at IS NULL OR returned_at >= loaned_at'
			.')'
		)->execute();
	}

	public function down()
	{
		\DBUtil::drop_table('loans');
	}
}
