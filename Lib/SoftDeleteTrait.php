<?php

trait SoftDeleteTrait {
	protected $_includeDeletedRecords = false;

	/**
	 * Must return name of flag field, e.g. "isDeleted"
	 * @return string
	 */
	abstract protected function _deletedFieldName();

	/**
	 * @param array $conditions
	 * @param string $re
	 * @return bool
	 */
	protected function _isDeletedFieldUsedInConditions($conditions, $re = '') {
		if (empty($conditions)) {
			return false;
		}

		if (is_string($conditions)) {
			$conditions = [$conditions];
		}

		if (!$re) {
			$re =
				'/^\s*`?('
				. $this->_deletedFieldName() . '|`?' . $this->alias . '`?.`?' . $this->_deletedFieldName()
				. '`?)`?\s*([><=]*|LIKE)\s*$/Ui'
			;
		}

		foreach ($conditions as $k => $v) {
			if (preg_match($re, $k)) {
				return true;
			}
			if (in_array(strtolower($k), ['or', 'and', 'not'])) {
				if ($this->_isDeletedFieldUsedInConditions($conditions[$k], $re)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return array
	 */
	protected function _conditionToExcludeDeletedRecords() {
		$field = "{$this->alias}.{$this->_deletedFieldName()}";
		switch ($this->getColumnType($this->_deletedFieldName())) {
			case 'boolean':
				return [$field => false];
			case 'date':
			case 'datetime':
				return [$field => null];
		}
		return [$field => null];
	}

	/**
	 * @param bool $quote
	 * @return array
	 */
	protected function _setDeletedFieldForDeletedRecord($quote = false) {
		switch ($this->getColumnType($this->_deletedFieldName())) {
			case 'boolean':
				return [$this->_deletedFieldName() => true];
			case 'datetime':
				$val = date('Y-m-d H:i:s');
				return [$this->_deletedFieldName() => $quote ? $this->getDatasource()->value($val) : $val];
			case 'date':
				$val = date('Y-m-d');
				return [$this->_deletedFieldName() => $quote ? $this->getDatasource()->value($val) : $val];
		}
		return [$this->_deletedFieldName() => true];
	}

	/**
	 * Additional data to save for deleted record
	 * @return array [field => value, ..]
	 */
	protected function _additionalFieldsForDeletedRecord() {
		return [];
	}

	/**
	 * @return mixed
	 */
	protected function _markDeleted() {
		$id = $this->id;
		$this->create(false);

		$fields = array_merge($this->_setDeletedFieldForDeletedRecord(), $this->_additionalFieldsForDeletedRecord());

		$options = ['validate' => false, 'fieldList' => array_keys($fields)];
		$fields[$this->primaryKey] = $id;

		return $this->save([$this->alias => $fields], $options);
	}

	/**
	 * Include deleted records in find results
	 */
	public function includeDeletedRecords() {
		$this->_includeDeletedRecords = true;
	}

	/**
	 * Exclude deleted records from find results
	 */
	public function excludeDeletedRecords() {
		$this->_includeDeletedRecords = false;
	}

	/**
	 * @param array $query
	 * @return array
	 */
	public function beforeFind($query) {
		if ($this->_includeDeletedRecords) {
			return $query;
		}

		if (!$this->_isDeletedFieldUsedInConditions($query['conditions'])) {
			$newCondition = $this->_conditionToExcludeDeletedRecords();
			$query['conditions'] = (array)$query['conditions'];
			$query['conditions'] += $newCondition;
		}

		return $query;
	}

	/**
	 * Removes record for given ID. If no ID is given, the current ID is used. Returns true on success.
	 * If first param is "hard" perform hard delete with 2 other params.
	 * @param int|string $id
	 * @param bool $cascade
	 * @return bool
	 */
	public function delete($id = null, $cascade = true) {
		if ($id === 'hard') {
			$args = func_get_args();
			$id = isset($args[1]) ? $args[1] : null;
			$cascade = isset($args[2]) ? $args[2] : null;
			return parent::delete($id, $cascade);
		}

		if (!empty($id)) {
			$this->id = $id;
		}

		$id = $this->id;

		$event = new CakeEvent('Model.beforeDelete', $this, [$cascade]);
		list($event->break, $event->breakOn) = [true, [false, null]];
		$this->getEventManager()->dispatch($event);
		if ($event->isStopped()) {
			return false;
		}

		if (!$this->exists()) {
			return false;
		}

		$this->_deleteDependent($id, $cascade);
		$this->_deleteLinks($id);
		$this->id = $id;

		if (!empty($this->belongsTo)) {
			foreach ($this->belongsTo as $assoc) {
				if (empty($assoc['counterCache'])) {
					continue;
				}

				$keys = $this->find('first', [
					'fields' => $this->_collectForeignKeys(),
					'conditions' => [$this->alias . '.' . $this->primaryKey => $id],
					'recursive' => -1,
					'callbacks' => false
				]);
				break;
			}
		}

		// ---
		if (!$this->_markDeleted()) {
			return false;
		}
		// ---

		if (!empty($keys[$this->alias])) {
			$this->updateCounterCache($keys[$this->alias]);
		}

		$this->getEventManager()->dispatch(new CakeEvent('Model.afterDelete', $this));
		$this->_clearCache();
		$this->id = false;

		return true;
	}

	/**
	 * @param mixed $conditions
	 * @param bool $cascade
	 * @param bool $callbacks
	 * @return bool
	 */
	public function deleteAll($conditions, $cascade = true, $callbacks = false) {
		if (empty($conditions)) {
			return false;
		}

		$ids = $this->find('all', array_merge([
			'fields' => "DISTINCT {$this->alias}.{$this->primaryKey}",
			'order' => false,
			'recursive' => 0], compact('conditions'))
		);

		if ($ids === false || $ids === null) {
			return false;
		}

		$ids = Hash::extract($ids, "{n}.{$this->alias}.{$this->primaryKey}");
		if (empty($ids)) {
			return true;
		}

		if (!$cascade && !$callbacks) {
			$res = true;
			foreach ($ids as $id) {
				$this->id = $id;
				$res = $res && $this->_markDeleted();
			}
			return $res;
		}

		if ($callbacks) {
			$_id = $this->id;
			$result = true;
			foreach ($ids as $id) {
				$result = $result && $this->delete($id, $cascade);
			}

			$this->id = $_id;
			return $result;
		}

		foreach ($ids as $id) {
			$this->_deleteLinks($id);
			if ($cascade) {
				$this->_deleteDependent($id, $cascade);
			}
		}

		// we need to escape additional fields manually to be used in updateAll method
		$additionalFields = $this->_additionalFieldsForDeletedRecord();
		if (!empty($additionalFields)) {
			foreach ($additionalFields as $addField => $addValue) {
				$additionalFields[$addField] = $this->getDatasource()->value($addValue);
			}
		}

		$fields = array_merge($this->_setDeletedFieldForDeletedRecord(true), $additionalFields);
		return $this->updateAll($fields, [$this->alias . '.' . $this->primaryKey => $ids]);
	}
}