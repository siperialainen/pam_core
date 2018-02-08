<?php

namespace PamCore\Assets;

use PamCore\Aws\S3;
use PamCore\Model;
use PamCore\Utils\Date;

class File extends Model
{
    const STORAGE_FOLDER = 'asset-files';
    const STORAGE_PREVIEW_FOLDER = 'preview-asset-files';

    /**
     * @var string
     */
    protected $tableName = 'asset_files';

    /**
     * @var string
     */
    protected $idColumn = 'id';

    const TYPE_DOCUMENT = 'document';
    const TYPE_ARTWORK = 'artwork';

    public function getTypes() {
        return [
            static::TYPE_DOCUMENT,
            static::TYPE_ARTWORK,
        ];
    }

    /**
     * @param int $assetId
     * @param string $category File category
     * @param string $localPath Local path to file
     * @param string $name Display file name
     * @param int $userId File owner/creator
     * @return array - File record
     * @throws Exception
     */
    public function saveAssetFile($assetId, $category, $localPath, $name, $userId)
    {
        $contentType = mime_content_type($localPath);
        $ref = S3::instance()->put($localPath, $contentType, null, $assetId, static::STORAGE_FOLDER);
        if (null === $ref) {
            throw new Exception("Can't store file in the S3 bucket");
        }
        $id = $this->insert([
            'assetId' => $assetId,
            'category' => $category,
            'name' => $name,
            'ref' => $ref,
            'created' => gmdate(Date::MYSQL_DATE_TIME_FORMAT, time()),
            'userId' => $userId,
        ]);

        return $this->getOne($id);
    }

    /**
     * @param array $data
     * @param bool $onDuplicateUpdate
     * @return int
     */
    public function insert($data, $onDuplicateUpdate = false)
    {
        $data += [
            'created' => gmdate(Date::MYSQL_DATE_TIME_FORMAT, time()),
        ];
        return parent::insert($data, $onDuplicateUpdate);
    }


    /**
     * @param $id
     * @throws Exception
     */
    public function delete($id)
    {
        $file = $this->getOne($id);
        if (!S3::instance()->delete($file['ref'], $file['assetId'], static::STORAGE_FOLDER)) {
            throw new Exception("Can't remove file from the S3 bucket");
        }
        parent::delete($id);
    }

    /**
     * @param $assetId
     * @return array
     */
    public function getAllByAssetId($assetId)
    {
        $result = $this->db->query(
            'SELECT f.*, u.first_name, u.last_name FROM `' . $this->tableName . '` as f ' .
            'JOIN users as u ' .
            'ON f.userId=u.id ' .
            'WHERE f.assetId=\'' . $assetId . '\''
        );

        $array = [];
        while ($row = $result->fetch_assoc()) {
            $array[] = $row;
        };

        return $array;
    }
}