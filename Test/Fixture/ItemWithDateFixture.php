<?php

class ItemWithDateFixture extends CakeTestFixture {
	var $name = 'ItemWithDate';
	var $table = 'itemsWithDates';
	var $fields = [
		'id'        => ['type' => 'integer', 'null' => false, 'default' => null, 'length' => 11, 'key' => 'primary'],
		'name'      => ['type' => 'string', 'null' => false, 'default' => null],
		'dateDeleted' => ['type' => 'datetime', 'null' => true, 'default' => null]
	];
	var $records = [
		['id' => 1, 'name' => 'A', 'dateDeleted' => null],
		['id' => 2, 'name' => 'B', 'dateDeleted' => '2014-01-01 01:01:01'],
		['id' => 3, 'name' => 'C', 'dateDeleted' => null]
	];
}