# CakePHP Soft Delete Trait

Soft delete trait for using with CakePHP 2.x models. 

Sometimes it is useful to flag records as deleted instead of physically removing them from the database. Achieving that with this plugin requires no custom delete method calls or custom find conditions to exclude deleted records - your models delete() and find()/read() will just work.  It is also possible to fetch deleted records when needed or perform "hard" delete.

## Requirements

* CakePHP 2.x
* PHP 5.4+

## Installation

### With Composer


```
"require": {
    "zunderbolt/cakephp-softdelete-trait": "dev-master"
}
```

### Manual

* Download files to `APP/Plugin/SoftDeleteTrait`

## Configuration

* Load plugin with `CakePlugin::load('SoftDeleteTrait');` in `bootstrap.php` or where needed.
* Show the app where to look for source file `App::uses('SoftDeleteTrait', 'SoftDeleteTrait.Lib');` or load it with your autoloader.
* Use the trait in your model and implement method `_deletedFieldName`, which should return the name of database field for deletion tracking.
* Don't forget to add the field in your database table. It can be boolean flag (TINYINT/INT field, 1 for deleted record, 0 otherwise), date or datetime (NULL for normal record, date/datetime of removal for deleted record).

Example:
```php
    App::uses('SoftDeleteTrait', 'SoftDeleteTrait.Lib');

    class Item extends AppModel {
        use SoftDeleteTrait;
        
        protected function _deletedFieldName() {
            return 'isDeleted';
        }
```

## Usage notes

Soft delete and exclusion of deleted records from find/read results works automagically.
To include deleted records in find results use $this->includeDeletedRecords() in your model. To exclude them again use the excludeDeletedRecords() method.

To save some additional data for deleted records you can implement protected method _additionalFieldsForDeletedRecord() in your model. For example, you can set record's status and removal reason:
```php
    protected function _additionalFieldsForDeletedRecord() {
        return [
            'removalReason' => 'Useless stuff',
            'status'        => self::STATUS_DELETED
        ];
    }
```