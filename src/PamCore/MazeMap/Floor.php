<?php
namespace PamCore\MazeMap;

use PamCore\Model;

class Floor extends Model
{
    protected $tableName = 'mazemap_floor';

    protected $idColumn = 'pamLevelId';

    /**
     * @param $levelId
     * @return bool
     */
    public function isMazeMapAvailableForLevel($levelId)
    {
        return !is_null($this->getOne($levelId));
    }
}