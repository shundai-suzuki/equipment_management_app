<?php

namespace Fuel\Migrations;

class Create_equipments
{
	public function up()
	{
		\DBUtil::create_table(
			'equipments', 
			array(
			'id' => array('constraint' => 11, 'type' => 'int', 'default' => '0'),
		), 
		array('id'));
	}

	public function down()
	{
		\DBUtil::drop_table('equipments');
	}
}