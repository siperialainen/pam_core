<?php
namespace PamCore\Assets;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use PamCore\Aws\DynamoDB;
use PamCore\Client;
use PamCore\Db\Utils;
use PamCore\Log;
use PamCore\Model;

class DigitalDirectory extends Model
{
    protected $tableName = 'digital_directory_asset';

    protected $idColumn = 'assetId';

    /**
     * Gets all digital directory assets grouped by building
     * @param array $orderBy
     * @return array [
     * 'assetId',
     * 'screenTypeId',
     * 'orientation',
     * 'isKioskModule',
     * 'description',
     * 'building',
     * 'level',
     * 'location'
     * ]
     */
    public function getAssetsGroupedByBuilding($orderBy = [])
    {
        $query = $this->getSelectAllQuery($orderBy);
        $result = mysqli_query($this->db, $query) or die(mysqli_error($this->db));
        $assetGroups = [];
        while($row = mysqli_fetch_assoc($result)) {
            $buildingCode = $row['building'];
            if (!isset($assetGroups[$buildingCode])) {
                $assetGroups[$buildingCode] = [];
            }
            $assetGroups[$buildingCode][$row['assetId']] = $row;
        };

        return $assetGroups;
    }

    /**
     * Gets all digital directory assets
     * @param array $orderBy
     * @param string $keyColumn Which column should be used to put as the key of result array
     * @return array [
     * 'assetId',
     * 'screenTypeId',
     * 'orientation',
     * 'isKioskModule',
     * 'description',
     * 'building',
     * 'level',
     * 'location'
     * ]
     */
    public function getAll($orderBy = [], $keyColumn = null)
    {
        return $this->fetchRows($this->getSelectAllQuery($orderBy));
    }

    public function createDefault($asset_id)
    {
        $ddData = [
            'assetId' => $asset_id,
            'screenTypeId' => array_keys((new ScreenType())->getAll())[0],
            'orientation' => array_keys(ScreenType::ORIENTATION)[0],
            'isKioskModule' => '0',
        ];
        $this->insert($ddData, true);
    }

    public function getAllByScreenType($screenTypeId)
    {
        global $db;
        $screenTypeId = mysqli_real_escape_string($db, $screenTypeId);

        $q = "SELECT * FROM digital_directory_asset dd WHERE dd.screenTypeId = '$screenTypeId'";
        return $this->fetchRows($q);
    }

    public function getAllByUserId($userId, $orderBy)
    {
        global $db;
        $userId = mysqli_real_escape_string($db, $userId);

        return $this->fetchRows($this->getSelectAllQuery($orderBy, "a2u.userId = '$userId'"));
    }

    public function getOneByUserIdAndAssetId($userId, $assetId)
    {
        global $db;
        $userId = mysqli_real_escape_string($db, $userId);
        $assetId = mysqli_real_escape_string($db, $assetId);

        $rows = $this->fetchRows($this->getSelectAllQuery('',
            "a2u.userId = '$userId' AND dd.assetId = '$assetId'"));
        return reset($rows);
    }



    /**
     * Get digital screen details
     * {
     *       "assetId": "18201",
     *       "screenTypeId": "4",
     *       "orientation": "landscape",
     *       "isKioskModule": "0",
     *       "userId": "0",
     *       "description": "TEST MODEL",
     *       "building": "06T",
     *       "level": "02",
     *       "location": "10",
     *       "template_id": "761",
     *       "asset_type_id": "406",
     *       "img_data": "321154ba080ef80dd731526e6dd777e40b3ae9e138112e096e4a0e14fecf1df777cba855e1523e0b47b4de0fb118aac205e189c4db973f71ca60b93e2e67a9f1",
     *       "width": "610",
     *       "asset_code_pre": "DG",
     *       "asset_code_suf": "DD",
     *       "screenModel": "TEST MODEL",
     *       "orientationLabel": "Landscape",
     *       "userName": "-",
     *       "previewSrc": "https:\/\/s3.eu-central-1.amazonaws.com\/mbpam-dev\/uts\/321154ba080ef80dd731526e6dd777e40b3ae9e138112e096e4a0e14fecf1df777cba855e1523e0b47b4de0fb118aac205e189c4db973f71ca60b93e2e67a9f1?X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIAIRX6Q747BYGZIJDA%2F20170216%2Feu-central-1%2Fs3%2Faws4_request&X-Amz-Date=20170216T164647Z&X-Amz-SignedHeaders=host&X-Amz-Expires=600&X-Amz-Signature=95e77ccb9d577bb33958babe60338b30d3284b0f42072aff6a14d2082419e9af",
     *       "previewWidth": "610"
     *     }
     * @param $assetId
     * @return array
     */
    public function getScreenDetailsByAssetId($assetId)
    {
        $safeAssetId = $this->db->real_escape_string($assetId);
        $q = <<<SQL
SELECT 
    dd.assetId, dd.screenTypeId, dd.orientation, dd.isKioskModule,    
    st.description,
    a.building, a.level, a.location, a.asset_number, a.template_id, a.asset_type_id,
    att.img_data, att.width,
    aty.asset_code_pre, aty.asset_code_suf
FROM digital_directory_asset dd 
JOIN digital_directory_screen_type st ON dd.screenTypeId = st.id
JOIN assets a ON a.asset_id = dd.assetId 
JOIN asset_type_templates att ON a.template_id = att.template_id 
JOIN asset_types aty ON a.asset_type_id = aty.asset_type_id 
WHERE dd.assetId = {$safeAssetId}
SQL;
        $res = $this->db->query($q);
        if (!$res) {
            die($this->db->error);
        }

        return mysqli_fetch_assoc($res);
    }

    /**
     * @return bool|\mysqli_result
     */
    private function getSelectAllQuery($orderBy, $where = '')
    {
        return "SELECT dd.assetId, dd.screenTypeId, dd.orientation, dd.isKioskModule, st.description, 
a.building, a.level, a.location, a.asset_number, a.template_id
FROM digital_directory_asset dd 
JOIN digital_directory_screen_type st ON dd.screenTypeId = st.id
JOIN assets a ON a.asset_id = dd.assetId
LEFT JOIN asset_2_user a2u ON (a.asset_id = a2u.assetId)"
        . ($where ? " WHERE {$where}" : '')
        . " GROUP BY dd.assetId"
        . Utils::arrayToOrderByStatement($orderBy);
    }

    public function getColors()
    {
        $clientId = Client::isClientInitiated() || isset($_SESSION['USER']) ? Client::get()->getId() : 'default';
        switch ($clientId) {
            case 'acu':
                return [
                    'bgHeader' => '#00ADEF',
                    'bgBody' => '#383839',
                    'bgEvent' => '#00ADEF',
                    'fgHeader' => 'white',
                    'fgBody' => '#DCDDDF',
                    'fgEvent' => 'white',
                    'lineBody' => 'white',
                ];
            case 'uc':
                return [
                    'bgHeader' => '#1C1A19',
                    'bgBody' => '#1C1A19',
                    'bgEvent' => '#3EA2CD',
                    'fgHeader' => 'white',
                    'fgBody' => 'white',
                    'fgEvent' => 'white',
                    'lineBody' => '#474745',
                ];
            case 'uts':
            default:
                return [
                    'bgHeader' => '#F6EA31',
                    'bgBody' => '#F6EA31',
                    'bgEvent' => '#52287D',
                    'fgHeader' => '#52287D',
                    'fgBody' => '#52287D',
                    'fgEvent' => '#FFFFFF',
                    'lineBody' => '#b3ae47',
                ];
        }
    }
    
    public function getAllByIds(array $ids) {
        $where = 'assetId IN (' . Utils::arrayToInStatement($ids, $this->db) . ')';
        return $this->getAllWhere($where);
    }

    public function saveDigitalDirectoryInDynamoDb($tableName, $hash, $data) {
        try {
            $marshaler = new Marshaler();
            $dataItem = $marshaler->marshalItem(
                [
                    "hash" => $hash,
                    "data" => $data,
                    "ttl" => DynamoDB::getDefaultTtl()
                ]
            );

            $params = [
                'TableName' => $tableName,
                'Item' => $dataItem
            ];
            $result = DynamoDB::instance()->getClient()->putItem($params);

            return $result;
        } catch (DynamoDbException $e) {
            Log::get()->addError("Error saving data for digital directory in DynamoDB: " . $e->getMessage());
            return null;
        }
    }

    public function getDigitalDirectoryInDynamoDb($tableName, $hash) {
        try {
            $marshaler = new Marshaler();
            $keyItem = $marshaler->marshalItem(
                [
                    "hash" => $hash
                ]
            );

            $params = [
                'TableName' => $tableName,
                'Key' => $keyItem
            ];
            $result = DynamoDB::instance()->getClient()->getItem($params);

            return $result;
        } catch (DynamoDbException $e) {
            Log::get()->addError("Error getting data for digital directory from DynamoDB: " . $e->getMessage());
            return null;
        }
    }

    public function removeDigitalDirectoryInDynamoDb($tableName, $hash) {
        try {
            $marshaler = new Marshaler();
            $keyItem = $marshaler->marshalItem(
                [
                    "hash" => $hash
                ]
            );

            $params = [
                'TableName' => $tableName,
                'Key' => $keyItem
            ];
            $result = DynamoDB::instance()->getClient()->deleteItem($params);
            return $result;
        } catch (DynamoDbException $e) {
            Log::get()->addError("Error removing data for digital directory from DynamoDB: " . $e->getMessage());
            return null;
        }
    }
}
