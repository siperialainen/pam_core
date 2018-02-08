<?php

namespace PamCore;

class Features
{
    const GOOGLE_MAPS = 'google_maps';
    const GOOGLE_MAPS_BEHIND_FLOOR_PLANS = 'google_maps_behind_floor_plans';
    const ENHANCED_LOCATION_DETAIL_REPORT = 'enhanced_location_detail_report';
    const KEY_DIAGRAM = 'key_diagram';
    const DESTINATION_DICTIONARY_ITEM_ICONS = 'destination_dictionary.item.icons';
    const MAZE_MAP = 'maze_map';
    const ASSET_ICON_ARROW_TRIANGLE = 'asset.icon.arrow_triangle';
    const PDF_TILED_FLOORPLAN = 'pdf.tiled_floorplan';
    const ASSET_PHASE_AUDIT = 'asset.phase.audit';
    const ASSET_PHASE_OVERRIDE = 'asset.phase.override';
    const UC_ROOM_NUMBER_DISPLAY_FORMAT = 'uc_room_number_display_format';
    const ASSET_ARROW_COLOR_SCHEME = 'asset.arrow.color_scheme';
    const UTS_ENABLE_BUILDING_HEADER = 'building_&_level_header_for_digital_directories';
    const BUILIDING_DIRECTORY_REVERSE_LEVELS_ORDER = 'building_directory_reverse_levels_order';

    public static function enabledDestinationDictionaryItemIcons()
    {
        return static::isFeatureAvailable(static::DESTINATION_DICTIONARY_ITEM_ICONS);
    }

    public static function enabledUtsEnableBuildingHeader()
    {
        return static::isFeatureAvailable(static::UTS_ENABLE_BUILDING_HEADER);
    }

    public static function isKeyDiagramAvailable()
    {
        return static::isFeatureAvailable(static::KEY_DIAGRAM);
    }

    public static function isGoogleMapsAvailable()
    {
        return static::isFeatureAvailable(static::GOOGLE_MAPS);
    }

    public static function isAuditPhaseAvailable()
    {
        return static::isFeatureAvailable(static::ASSET_PHASE_AUDIT);
    }

    public static function isAssetPhaseOverrideAvailable()
    {
        return static::isFeatureAvailable(static::ASSET_PHASE_OVERRIDE);
    }

    public static function isGoogleMapsBehindFloorPlansAvailable()
    {
        return static::isFeatureAvailable(static::GOOGLE_MAPS_BEHIND_FLOOR_PLANS);
    }

    public static function isBuildingDirectoryReverseLevelsOrderAvailable()
    {
        return static::isFeatureAvailable(static::BUILIDING_DIRECTORY_REVERSE_LEVELS_ORDER);
    }

    public static function isFeatureAvailable($name)
    {
        global $db;

        $name = $db->real_escape_string($name);
        $q = "SELECT 1 FROM `features_enabled` fe
LEFT JOIN features f ON (f.id = fe.feature_id)
WHERE f.name = '$name'";
        $res = $db->query($q);
        return (bool) $res->num_rows;
    }

    public static function setFeatureAvailable($id, $enabled)
    {
        global $db;

        $id = $db->real_escape_string($id);
        if (!$enabled) {
            $q = "DELETE FROM `features_enabled` WHERE feature_id = '$id';";
        } else {
            $q = "INSERT INTO `features_enabled` (`feature_id`) VALUES ($id) ON DUPLICATE KEY UPDATE `feature_id`=$id";
        }

        return $db->query($q);
    }

    /**
     * Return all features with enabled attribute
     *
     * @return array
     */
    public static function getAll()
    {
        global $db;

        $q = "SELECT * FROM `features` f LEFT JOIN `features_enabled` fe ON (f.id = fe.feature_id)";
        $res = $db->query($q);
        $features = $res->fetch_all(MYSQLI_ASSOC);
        foreach($features as &$feature) {
            $feature['enabled'] = $feature['feature_id'] > 0 ? true : false;
        }

        return $features;
    }
}