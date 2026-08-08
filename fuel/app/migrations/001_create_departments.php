<?php

namespace Fuel\Migrations;

class Create_departments
{
	public function up()
	{
		\Config::set('db.default.collation', 'utf8mb4_unicode_ci');
		
		\DBUtil::create_table(
			'departments', 
			array(
				'id' => array('type' => 'int', 'constraint' => 11, 'null' => false, 'default' => 0),
				'name' => array('type' => 'varchar', 'constraint' => 255, 'null' => false),
				'created_at' => array('type' => 'timestamp', 'null' => true),
				'updated_at' => array('type' => 'timestamp', 'null' => true),
				'deleted_at' => array('type' => 'timestamp', 'null' => true),
		), array('id'), false, 'InnoDB', 'utf8mb4');

		\DBUtil::create_index(
			'departments',
			array('name'),
			'uq_departments_name',
			'UNIQUE'
		);

		$table = \DB::quote_identifier(\DB::table_prefix('departments'));
		\DB::query(
			'ALTER TABLE '.$table
			.' ADD CONSTRAINT chk_departments_id_positive CHECK (id > 0)'
		)->execute();
	}

	public function down()
	{
		\DBUtil::drop_table('departments');
	}
}
