<?php
namespace PamCore\Geo;

use PamCore\Model;

class LocationModel extends Model
{
    const TABLE_NAME = 'geo_location';

    protected $tableName = 'geo_location';

    protected $idColumn = 'id';

    public function save(LocationEntity $entity)
    {
        if ($entity->getId()) {
            parent::update($entity->getId(), $entity->getArray());
        } else {
            $entity->setId(parent::insert($entity->getArray()));
        }
        return $entity;
    }

    public function deleteBy($condition = '') {
        $query = "DELETE `{$this->tableName}` FROM `{$this->tableName}` {$condition}";
        return mysqli_query($this->db, $query);
    }

    public function deleteByAssets($whereCondition = '') {
        $tableName = $this->tableName;
        return $this->deleteBy("JOIN `assets` ON `assets`.geoLocationId = `{$tableName}`.id {$whereCondition}");
    }

    public function deleteByMarkers($whereCondition = '') {
        $tableName = $this->tableName;
        return $this->deleteBy("JOIN `markers` ON `markers`.geoLocationId = `{$tableName}`.id {$whereCondition}");
    }

}