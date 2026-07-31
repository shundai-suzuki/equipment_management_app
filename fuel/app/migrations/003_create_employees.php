<?php

namespace Fuel\Migrations;

class Create_employees
{
	public function up()
	{
		\DBUtil::create_table(
			'employees', 
			array(
			'id' => array('constraint' => 11, 'type' => 'int', 'default' => '0'),

		),
		array('id'));
	}

	public function down()
	{
		\DBUtil::drop_table('employees');
	}
}