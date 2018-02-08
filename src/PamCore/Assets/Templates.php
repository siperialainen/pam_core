<?php
namespace PamCore\Assets;

use PamCore\Model;

class Templates extends Model
{
    protected $tableName = 'asset_type_templates';

    protected $idColumn = 'template_id';

    public static function getIntNumber($templateNumber){
        if (empty($templateNumber)) {
            $templateNumber = 1;
        } else if (!is_numeric($templateNumber)) {
            $alfabet = range('A', 'Z');
            $alfabet = implode('', $alfabet);
            $templateNumber = strpos($alfabet, $templateNumber) + 1;
            if (!$templateNumber) $templateNumber = 1;
        }

        return $templateNumber;
    }

    public function getOneByTemplateNumber($assetCode, $templateNumber) {
        $assetCode = $this->db->real_escape_string($assetCode);
        $templateNumber = $this->db->real_escape_string($templateNumber);

        $q = "SELECT $this->tableName.* FROM $this->tableName
JOIN asset_types USING (asset_type_id)
WHERE asset_types.asset_code = '$assetCode' AND template_number = '$templateNumber'";

        $result = $this->db->query($q);
        if ($result) {
            return $result->fetch_assoc();
        }
        return null;
    }

    /**
     * @param array $templates
     * ['PRE' => [
     *  'SUF' => [1,2]
     *  ]
     * ]
     * @param string $pre Asset Code Prefix
     * @param string $suf Asset Code Suffix
     * @param string $number Asset Type Template Number
     * @return bool
     */
    public static function inConfigArray($templates, $pre, $suf, $number)
    {
        $number = static::getIntNumber($number);
        return array_key_exists($pre, $templates) &&
            array_key_exists($suf, $templates[$pre]) &&
            is_array($templates[$pre][$suf]) &&
            in_array($number, $templates[$pre][$suf]);
    }

    /**
     * @param array $templates
     * ['PRE' => [
     *      'SUF' =>
     *      [
     *          1 => [...config...],
     *          2 => [...config...]
     *      ]
     *   ]
     * ]
     * @param $pre
     * @param $suf
     * @param $number
     * @return array
     */
    public static function getConfigFromArray($templates, $pre, $suf, $number) {
        $config = [];
        $number = static::getIntNumber($number);
        if (array_key_exists($pre, $templates) &&
            array_key_exists($suf, $templates[$pre]) &&
            array_key_exists($number, $templates[$pre][$suf])
        ) {
            $config = $templates[$pre][$suf][$number];
        }

        return $config;
    }

    public function getOneByAssetId($assetId)
    {
        $assetId = $this->db->real_escape_string($assetId);
        $q = "SELECT t.* FROM `assets` a 
JOIN `{$this->tableName}` t USING(template_id)
WHERE a.asset_id = '$assetId' LIMIT 1";
        $res = $this->db->query($q);
        if (!$res) {
            return null;
        }

        return $res->fetch_assoc();
    }

    /**
     * Return all first templates in array with asset_type_id as key
     *
     * @return array
     */
    public function getAllFirst() {
        $templates = $this->getAllWhere("template_number = 1 OR template_number = 'A'");
        $result = [];
        foreach($templates as $template) {
            $result[$template['asset_type_id']] = $template;
        }

        return $result;
    }
}