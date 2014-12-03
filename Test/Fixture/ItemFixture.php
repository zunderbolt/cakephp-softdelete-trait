<?php

class ItemFixture extends CakeTestFixture {
	const DESCR_OK      = 'normal';
	const DESCR_DELETED = 'deleted';

	public $name = 'Item';
	public $table = 'items';
	public $fields = [
		'id'          => ['type' => 'integer', 'null' => false, 'default' => null, 'length' => 11, 'key' => 'primary'],
		'name'        => ['type' => 'string', 'null' => false, 'default' => null],
		'isDeleted'   => ['type' => 'boolean', 'null' => false, 'default' => 0],
		'description' => ['type' => 'string', 'null' => false, 'default' => self::DESCR_OK]
	];
	public $records = [
		['id' => 1, 'name' => 'A', 'isDeleted' => 0, 'description' => self::DESCR_OK],
		['id' => 2, 'name' => 'B', 'isDeleted' => 1, 'description' => self::DESCR_DELETED],
		['id' => 3, 'name' => 'C', 'isDeleted' => 0, 'description' => self::DESCR_OK]
	];
}