<?php

namespace PamCore\Geo;

use PamCore\Model;

class MapTilesStatus extends Model
{
    const PENDING = 'pending';
    const PROCESSING = 'processing';
    const SUCCESS = 'success';
    const ERROR = 'error';

    protected $tableName = 'map_tiles_status';
    protected $idColumn = 'id';

    public function setStatus($uniqueId, $status, $statusMessage = '')
    {
        return $this->insert([
            'unique_id' => $uniqueId,
            'status' => $status,
            'statusMessage' => $statusMessage,
        ], true);
    }

    public function getStatus($uniqueId)
    {
        if (empty($uniqueId)) {
            return null;
        }
        $status = $this->getOneByFields(['unique_id' => $uniqueId]);
        if (is_null($status)) {
            return [
                'unique_id' => $uniqueId,
                'status' => static::SUCCESS,
                'statusMessage' => '',
            ];
        }

        return $status;
    }
}