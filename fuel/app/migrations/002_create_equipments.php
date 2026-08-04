<?php

namespace Fuel\Migrations;

class Create_equipments
{
	public function up()
	{
		\Config::set('db.default.collation', 'utf8mb4_unicode_ci');
		
		\DBUtil::create_table(
			'equipments', 
			array(
				'id' => array('type' => 'int', 'constraint' => 11, 'null' => false, 'default' => 0),
				'name' => array('type' => 'varchar', 'constraint' => 255, 'null' => false),
				'department_id' => array('type' => 'int', 'constraint' => 11, 'null' => false, 'default' => 0),
				'category' => array('type' => 'varchar', 'constraint' => 20, 'null' => false),
				'total_amount' => array('type' => 'int', 'constraint' => 10, 'null' => false),
				'description' => array('type' => 'varchar', 'constraint' => 255, 'null' => true, 'default' => null),
				'created_at' => array('type' => 'timestamp', 'null' => true),
				'updated_at' => array('type' => 'timestamp', 'null' => true),
				'deleted_at' => array('type' => 'timestamp', 'null' => true),
		), array('id'), false, 'InnoDB', 'utf8mb4');

		\DBUtil::add_foreign_key(
			'equipments', 
			array(
				'constraint' => 'fk_equipments_department',
				'key' => 'department_id',
				'reference' => array(
					'table' => 'departments',
					'column' => 'id',
				),
				'on_update' => 'RESTRICT',
				'on_delete' => 'RESTRICT',
		));

		\DBUtil::create_index(
			'equipments',
			array('department_id', 'category', 'deleted_at'),
			'idx_equipments_department_category_deleted'
		);
		\DBUtil::create_index(
			'equipments',
			array('department_id', 'name'),
			'uq_equipments_department_name',
			'UNIQUE'
		);

		$table = \DB::quote_identifier(\DB::table_prefix('equipments'));
		\DB::query(
			'ALTER TABLE '.$table
			.' ADD CONSTRAINT chk_equipments_id_positive CHECK (id > 0),'
			.' ADD CONSTRAINT chk_equipments_category CHECK ('
			.'CHAR_LENGTH(TRIM(category)) BETWEEN 1 AND 20 '
			.'AND CHAR_LENGTH(category) = CHAR_LENGTH(TRIM(category))'
			.'),'
			.' ADD CONSTRAINT chk_equipments_total_amount CHECK (total_amount >= 1)'
		)->execute();
	}

	public function down()
	{
		\DBUtil::drop_table('equipments');
	}
}
