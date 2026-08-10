<?php

namespace Fuel\Migrations;

class Create_employees
{
	public function up()
	{
		\Config::set('db.default.collation', 'utf8mb4_unicode_ci');
		
		\DBUtil::create_table(
			'employees', 
			array(
				'id' => array('type' => 'int', 'constraint' => 11, 'null' => false, 'auto_increment' => true),
				'employee_name' => array('type' => 'varchar', 'constraint' => 30, 'null' => false),
				'department_id' => array('type' => 'int', 'constraint' => 11, 'null' => false),
				'role' => array('type' => 'varchar', 'constraint' => 20, 'null' => false),
				'password_hash' => array('type' => 'varchar', 'constraint' => 255, 'null' => false),
				'is_active' => array('type' => 'int', 'constraint' => 1, 'null' => false, 'default' => 1),
				'created_at' => array('type' => 'timestamp', 'null' => true),
				'updated_at' => array('type' => 'timestamp', 'null' => true),
				'deleted_at' => array('type' => 'timestamp', 'null' => true),
		), array('id'), false, 'InnoDB', 'utf8mb4');

		\DBUtil::add_foreign_key(
			'employees', 
			array(
				'constraint' => 'fk_employees_department',
				'key' => 'department_id',
				'reference' => array(
					'table' => 'departments',
					'column' => 'id',
				),
				'on_update' => 'RESTRICT',
				'on_delete' => 'RESTRICT',
		));

		\DBUtil::create_index(
			'employees',
			array('department_id', 'deleted_at', 'is_active'),
			'idx_employees_department_deleted_active'
		);

		$table = \DB::quote_identifier(\DB::table_prefix('employees'));
		\DB::query(
			'ALTER TABLE '.$table
			.' ADD CONSTRAINT chk_employees_role CHECK (role IN (\'EMPLOYEE\', \'ADMIN\')),'
			.' ADD CONSTRAINT chk_employees_is_active CHECK (is_active IN (0, 1))'
		)->execute();
	}

	public function down()
	{
		\DBUtil::drop_table('employees');
	}
}
