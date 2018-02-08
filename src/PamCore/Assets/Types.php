<?php
namespace PamCore\Assets;

use PamCore\Email\GoogleSMTP;
use PamCore\Model;
use PamCore\Db\Utils;
use PamCore\Assets\Templates;

class Types extends Model
{
    protected $tableName = 'asset_types';

    protected $idColumn = 'asset_type_id';

    const WITH_ARROWS = ['D 1f', 'D 3f', 'D 3w', 'D 4w', 'D 5s', 'D 5w'];
    const WITH_LEVELS = ['N 2w', 'N 3w'];
    const WITH_ROOMS = ['D 2f'];
    const WITH_GROUP_ROOMS = ['D 3f', 'D 3w', 'D 4w', 'D 5s', 'D 5w'];
    const WITH_BUILDING = ['D 4w' => 'B', 'D 5s' => 'B', 'D 5w' => 'B'];

    /**
     * This affects behaviour of turning on translation
     */
    const COPY_TRANSLATION_ON_THE_NEXT_LINE = [
        'F' => [
            'LD' => [1, 2]
        ]
    ];

    const NUMBER_BUILDING_SLATS = [
        'B' => [
            'ID1' => [
                1 => 1
            ],
            'ID2' => [
                1 => 1
            ],
            'ID3' => [
                1 => 1,
                2 => 1,
                3 => 2,
                4 => 2
            ],
            'ID4' => [
                1 => 1,
                2 => 1,
            ]
        ]
    ];

    const BUILDING_SLAT_CODE_BY_DEFAULT = [
        'B' => [
            'ID4' => [
                1, 2
            ]
        ]
    ];

    public static function groupAssetTypesByCategories($assetTypes, $categories)
    {
        $result = [];

        foreach ($assetTypes as $assetType) {
            $categoryId = $assetType['asset_cat_id'];
            if (!isset($result[$categoryId])) {
                $result[$categoryId] = $categories['cat_' . $categoryId];
                $result[$categoryId]['asset_types'] = [];
            }
            $result[$categoryId]['asset_types'][] = $assetType;
        }

        return $result;
    }

    public static function makeAssetTypesDescription($assetIds, $assetTypes, $categories)
    {
        if (count($assetIds) == count($assetTypes) || empty($assetIds)) {
            return 'ALL';
        }
        $categoriesWithAssets = static::groupAssetTypesByCategories($assetTypes, $categories);
        $categories = [];
        foreach ($categoriesWithAssets as $category) {
            $assetTypes = [];
            foreach ($category['asset_types'] as $assetType) {
                if (in_array($assetType['asset_type_id'], $assetIds)) {
                    $assetTypes[] = $assetType['asset_code_suf'];
                }
            }
            if (count($assetTypes) == 0) {
                continue;
            }
            if (count($assetTypes) == count($category['asset_types'])) {
                $categories[] = $category['cat_code'];
            } else {
                $categories[] = $category['cat_code'] . ' ' . implode(', ', $assetTypes);
            }
        }

        return implode('; ', $categories);
    }

    public static function fitAssetTypeDescription($description, $length=18)
    {
        if (strlen($description) <= $length) {
            return $description;
        }
        $description = substr($description, 0, $length);

        $semicolonPos = strrpos($description, ';');
        $commaPos = strrpos($description, ',');
        return substr_replace($description, '...', $semicolonPos > $commaPos ? $semicolonPos : $commaPos);
    }

    public static function getByCode($code)
    {
        global $db;
        $code = mysqli_real_escape_string($db, $code);
        $q = "SELECT * FROM asset_types WHERE asset_code = '$code'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            return null;
        }
        return mysqli_fetch_assoc($res);
    }

    public static function isWatchedByUser($assetTypeId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetTypeId = mysqli_real_escape_string($db, $assetTypeId);
        $q = "SELECT * FROM asset_type_watchers WHERE asset_type_id = '$assetTypeId' AND user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }

        return (bool)mysqli_fetch_assoc($res);
    }

    public static function addWatcher($assetTypeId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetTypeId = mysqli_real_escape_string($db, $assetTypeId);
        $q = "REPLACE INTO asset_type_watchers SET asset_type_id = '$assetTypeId', user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }
    }

    public static function removeWatcher($assetTypeId, $userId=null)
    {
        global $db;
        if (!$userId) {
            global $USER_ID;
            $userId = $USER_ID;
        }
        $userId = mysqli_real_escape_string($db, $userId);
        $assetTypeId = mysqli_real_escape_string($db, $assetTypeId);
        $q = "DELETE FROM asset_type_watchers WHERE asset_type_id = '$assetTypeId' AND user_id = '$userId'";
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }
    }

    /**
     * @param $assetTypeId
     * @return array of usernames of asset type's watchers
     */
    public static function getWatchers($assetTypeId)
    {
        global $db;
        $assetTypeId = mysqli_real_escape_string($db, $assetTypeId);
        $q = "SELECT username FROM asset_type_watchers aw JOIN users u ON aw.user_id = u.id 
WHERE aw.asset_type_id = '$assetTypeId' AND u.enabled = 'Y'";
        $watchers = [];
        $res = mysqli_query($db, $q);
        if (!$res) {
            die(mysqli_error($db));
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $watchers[] = $row['username'];
        }
        return $watchers;
    }

    public static function notifyWatchers($assetTypeId, $comment)
    {
        $watchers = static::getWatchers($assetTypeId);
        if (count($watchers) == 0) {
            return;
        }
        $assetType = get_asset_type($assetTypeId);
        $text = "$comment<br>{$assetType['asset_code']}";
        foreach ($watchers as $watcher) {
            GoogleSMTP::instance()->mail("MediabankPAM<pam@mediabankpam.com>", $watcher, $comment, $text);
        }
    }

    public function getAllFiltered(array $assetTypeCatShowType) {
        $result = $this->db->query(
            'SELECT * FROM `' . $this->tableName . '` as at ' .
            'JOIN asset_type_categories as atct ' .
            'ON atct.asset_cat_id=at.asset_cat_id ' .
            'AND atct.directory_show_type IN (' . Utils::arrayToInStatement($assetTypeCatShowType, $this->db) . ')'
        );

        $array = [];
        while ($row = $result->fetch_assoc()) {
            $array[] = $row;
        };

        return $array;
    }

    public static function isCopyTranslationNextLineDuringAdding($assetCodePre, $assetCodeSuf, $templateNumber)
    {
        return Templates::inConfigArray(static::COPY_TRANSLATION_ON_THE_NEXT_LINE, $assetCodePre, $assetCodeSuf, $templateNumber);
    }

    public function getOneByAssetId($assetId)
    {
        $assetId = $this->db->real_escape_string($assetId);
        $q = "SELECT at.* FROM `assets` a 
JOIN `{$this->tableName}` at USING(asset_type_id)
WHERE a.asset_id = '$assetId' LIMIT 1";
        $res = $this->db->query($q);
        if (!$res) {
            return null;
        }

        return $res->fetch_assoc();
    }

    public static function hasPreview($assetType, $assetTemplate)
    {
        if (!$assetType) {
            return false;
        }
        $preview = get_asset_preview($assetType['asset_code_pre'], $assetType['asset_code_suf']);
        if ($preview == "template" && $assetTemplate && $assetTemplate['preview_type'] == "n/a") {
            $preview = "no_preview";
        }
        if ($preview != 'no_preview' && file_exists($_SERVER["DOCUMENT_ROOT"] . '/assets/asset_previews/' . $preview . '.php')) {
            return true;
        }

        return false;
    }

    public static function getNumberOfBuildingSlats($codePre, $codeSuf, $templateNumber) {
        return (int)Templates::getConfigFromArray(Types::NUMBER_BUILDING_SLATS, $codePre, $codeSuf, $templateNumber);
    }

    public static function isBuildingSlatCodeByDefault($assetCodePre, $assetCodeSuf, $templateNumber) {
        return Templates::inConfigArray(static::BUILDING_SLAT_CODE_BY_DEFAULT, $assetCodePre, $assetCodeSuf, $templateNumber);
    }
}