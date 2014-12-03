<?php
App::uses('AppModel', 'Model');
App::uses('SoftDeleteTrait', 'SoftDeleteTrait.Lib');

class Item extends CakeTestModel  {
	use SoftDeleteTrait;

	public $name = 'Item';

	protected function _deletedFieldName() {
		return 'isDeleted';
	}

	protected function _additionalFieldsForDeletedRecord() {
		return ['description' => ItemFixture::DESCR_DELETED];
	}

	public function findField($conditions) {
		return $this->_isDeletedFieldUsedInConditions($conditions);
	}
}

class ItemWithDate extends CakeTestModel  {
	use SoftDeleteTrait;

	public $name = 'ItemWithDate';
	public $useTable = 'itemsWithDates';

	protected function _deletedFieldName() {
		return 'dateDeleted';
	}
}

class ItemTest extends CakeTestCase {

	public $fixtures = [
		'plugin.SoftDeleteTrait.item',
		'plugin.SoftDeleteTrait.itemWithDate'
	];

	/**
	 * @var Item
	 */
	protected $Item;

	/**
	 * @var ItemWithDate
	 */
	protected $ItemWithDate;

	public function setUp() {
		parent::setUp();
		$this->Item = ClassRegistry::init('SoftDeleteTrait.Item');
		$this->ItemWithDate = ClassRegistry::init('SoftDeleteTrait.ItemWithDate');
	}

	protected function _getIds($items) {
		return Hash::extract($items, '{n}.{s}.id');
	}

	public function testShouldFindOnlyNondeletedRecords() {
		$this->assertEqual($this->_getIds($this->Item->find('all')), [1,3]);
		$this->Item->delete(1);
		$this->assertEqual($this->_getIds($this->Item->find('all')), [3]);

		$this->assertEqual($this->_getIds($this->ItemWithDate->find('all')), [1,3]);
		$this->ItemWithDate->delete(1);
		$this->assertEqual($this->_getIds($this->ItemWithDate->find('all')), [3]);
	}

	public function testShouldFindAllRecordsIfNeededAndOnlyIfNeeded() {
		$this->Item->includeDeletedRecords();
		$this->assertEqual($this->_getIds($this->Item->find('all')), [1,2,3]);
		$this->Item->excludeDeletedRecords();
		$this->assertEqual($this->_getIds($this->Item->find('all')), [1,3]);
	}

	public function testShouldSetCorrectFieldValues() {
		$this->Item->delete(1);
		$this->Item->includeDeletedRecords();
		$item = $this->Item->read(null, 1);
		$this->assertEqual($item['Item']['isDeleted'], 1);
		$this->assertEqual($item['Item']['description'], ItemFixture::DESCR_DELETED);

		$date = new DateTime();
		$this->ItemWithDate->delete(1);
		$this->ItemWithDate->includeDeletedRecords();
		$item = $this->ItemWithDate->read(null, 1);
		$dateDiff = $date->diff(new DateTime($item['ItemWithDate']['dateDeleted']));
		$this->assertEqual($dateDiff->days, 0);
		$this->assertTrue($dateDiff->s <= 1);
	}

	public function testShouldDeleteForeverIfAsked() {
		$this->Item->delete('hard', 1);
		$this->Item->includeDeletedRecords();
		$this->assertEmpty($this->Item->read(null, 1));
	}

	public function testShouldDeleteAllCorrectly() {
		$this->Item->deleteAll(['Item.id' => [1,3]]);
		$this->assertEmpty($this->Item->find('all'));
		$this->Item->includeDeletedRecords();
		$item = $this->Item->read(null, 1);
		$this->assertEqual($item['Item']['description'], ItemFixture::DESCR_DELETED);
	}

	public function testShouldCorrectlyFindFieldInConditions() {
		$this->assertFalse($this->Item->findField([]));
		$this->assertFalse($this->Item->findField(['id' => '123']));
		$this->assertFalse($this->Item->findField(['id' => '123', 'OR' => ['adsda', 'asdsad']]));
		$this->assertFalse($this->Item->findField(['isDeleted3' => '123']));
		$this->assertFalse($this->Item->findField(['isDeleted SADJASD' => '123']));

		$this->assertTrue($this->Item->findField(['isDeleted' => '123']));
		$this->assertTrue($this->Item->findField(['Item.isDeleted' => '123']));
		$this->assertTrue($this->Item->findField(['`Item`.isDeleted' => '123']));
		$this->assertTrue($this->Item->findField(['Item.`isDeleted`' => '123']));
		$this->assertTrue($this->Item->findField(['`Item`.`isDeleted`' => '123']));
		$this->assertTrue($this->Item->findField(['isDeleted >' => '123']));
		$this->assertTrue($this->Item->findField(['isDeleted > ' => '123']));
		$this->assertTrue($this->Item->findField(['isDeleted >= ' => '123']));
		$this->assertTrue($this->Item->findField(['isDeleted <=' => '123']));
		$this->assertTrue($this->Item->findField(['isDeleted LIKE ' => '123']));
		$this->assertTrue($this->Item->findField(['id' => '123', 'OR' => [' `isDeleted` > ' => '13213']]));
		$this->assertTrue($this->Item->findField(['id' => '123', 'OR' => [' Item.isDeleted LIKE ' => '13213']]));
	}
}