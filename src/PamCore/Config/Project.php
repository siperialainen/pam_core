<?php
namespace PamCore\Config;

use PamCore\Model;

class Project extends Model
{
    const PARAM_PROJECT_NAME = 'project_name';
    const PARAM_STAFF_CAT_ID = 'staff_cat_id';
    const PARAM_AUDIT_BRIEF = 'audit_brief';
    const PARAM_INDUSTRY_ID = 'industry_id';
    const PARAM_SHOW_SETTINGS_WIZARD = 'show_settings_wizard';
    const PARAM_SHOW_ENVIRONMENT_WIZARD = 'show_environment_wizard';
    const PARAM_ENVIRONMENT_WIZARD_L_ID = 'environment_wizard_l_id';
    const PARAM_SEARCHABLE_SWS_PREFIXES = 'searchable_sws_prefixes';
    const PARAM_ASSET_IDENTIFIER_TYPE = 'asset_identifier_type';
    const PARAM_ASSET_LAST_NUMBER = 'asset_last_number';
    const PARAM_CONFLUENCE_SPACE = 'confluence_space';

    protected $tableName = 'project_variables';
    protected $idColumn = 'name_id';

    /**
     * @var Project
     */
    private static $instance;

    /**
     * @return Project
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function getParam($name)
    {
        $row = $this->getOne($name);
        if ($row) {
            return $row['val'];
        }
        return null;
    }

    public function setParam($name, $value)
    {
        $row = $this->getOne($name);
        if ($row) {
            $row['val'] = $value;
            $this->update($name, $row);
        } else {
            $row = ['name_id' => $name, 'val' => $value];
            $this->insert($row);
        }
    }
}