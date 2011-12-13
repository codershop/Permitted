<?php
class PermittedSchema extends CakeSchema {

	var $name = 'Permitted';

	function before($event = array()) {
		return true;
	}

	function after($event = array()) {
	}

	var $permitted = array(
			'id' => array('type'=>'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'),
			'alias' => array('type'=>'string', 'null' => true),
			'foreign_key' => array('type'=>'integer', 'null' => true, 'default' => NULL, 'length' => 10),
			'model' => array('type'=>'string', 'null' => true),
			'path' => array('type'=>'string', 'null' => true),
			'type' => array('type'=>'string', 'null' => true),
			'indexes' => array('PRIMARY' => array('column' => 'id', 'unique' => 1))
		);

}
