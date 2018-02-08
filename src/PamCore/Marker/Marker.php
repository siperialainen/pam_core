<?php

namespace PamCore\Marker;

class Marker
{
    /**
     * @param $bCode
     * @param null $lCode
     * @param null $type
     * @todo $liftAsMobile add as workaround to close urgent DEV-2994, need research and implement better solution
     * @param bool $liftAsMobile
     * @return array
     */
    public static function getLandmarksDirectory($bCode, $lCode = null, $type = null, $liftAsMobile = false)
    {
        global $db;
        $q = "SELECT directory_data, b_code FROM site_buildings WHERE b_code = '" . mysqli_real_escape_string($db, $bCode) . "'";
        $q_result = mysqli_query($db, $q);
        while ($row = mysqli_fetch_assoc($q_result)) {
            $data_json = $row['directory_data'];
            $directory_data[$row['b_code']] = json_decode($data_json, true);
        }

        $array = [];
        $landmarks = static::getActiveLandmarksLikeAssets($bCode, $lCode);
        foreach ($landmarks as $lmId => $lmData) {
            $key = 'lm_' . $lmId;
            $lmData['s_l'] = !isset(
                $directory_data[$lmData['b_code']],
                $directory_data[$lmData['b_code']][$key],
                $directory_data[$lmData['b_code']][$key]['s_l']
            ) || 'Y' != $directory_data[$lmData['b_code']][$key]['s_l'] ? 'N' : 'Y';
            $lmData['s_m'] = !isset(
                $directory_data[$lmData['b_code']],
                $directory_data[$lmData['b_code']][$key],
                $directory_data[$lmData['b_code']][$key]['s_m']
            ) || 'Y' != $directory_data[$lmData['b_code']][$key]['s_m'] ? 'N' : 'Y';
            if (('MOBILE' == $type && 'N' == $lmData['s_m']) ||
                ('LIFT' == $type && 'N' == $lmData['s_l'] )) {
                continue;
            }
            /**
             * @todo $liftAsMobile add as workaround to close urgent DEV-2994, need research and implement better solution
             */
            if ($type == 'MOBILE' || $liftAsMobile) {
                $array[$key] = [
                    $lmId => $lmData
                ];
            } else {
                $array[$key] = $lmData;
            }
        }

        return $array;
    }

    /**
     * Return active icons markers with 'building' location type
     *
     * @param string|null $bCode
     * @param string|null $lCode
     * @return array
     */
    public static function getActiveLandmarksLikeAssets($bCode = null, $lCode = null)
    {
        global $db;
        $q = "SELECT *, gl.* FROM `markers`
LEFT JOIN `marker_type_categories` mtc USING (marker_cat_id)
LEFT JOIN `geo_location` gl ON geoLocationId = gl.id
WHERE mtc.cat_type = 'IC' AND location_type = 'building' AND marker_status = 'ACTIVE'";

        if ($bCode) {
            $q .= " AND location_id = '$bCode' ";
        }

        if ($lCode) {
            $q .= " AND plan_id = '$lCode' ";
        }

        $r = mysqli_query($db, $q);
        $icons = get_icons('all', 'icon_name_id');

        $array = [];
        while ($row = mysqli_fetch_assoc($r)) {
            if (empty($row['marker_data'])) continue;
            $markerData = json_decode($row['marker_data'], true);
            $row['latitude'] = floatval($row['latitude']);
            $row['longitude'] = floatval($row['longitude']);
            if (!isset($markerData['icon']) || !isset($icons['icon_' . $markerData['icon']])) continue;

            $row = static::prepareLikeAsset($row);
            $array[$row['marker_id']] = $row;
        };

        return $array;
    }

    private static function prepareLikeAsset($marker)
    {
        $markerData = json_decode($marker['marker_data'], true);
        $icons = get_icons('all', 'icon_name_id');
        $marker['destination_id'] = 'lm_' . $marker['marker_id'];
        if (is_array($markerData) && isset($markerData['icon']) && isset($icons['icon_' . $markerData['icon']])) {
            $marker['icon_name_id'] = $markerData['icon'];
            $marker['copy']['en'] = $icons['icon_' . $markerData['icon']]['icon_name'];
            $marker['en'] = $marker['copy']['en'];
        } else {
            $marker['icon_name_id'] = '';
            $marker['copy']['en'] = '';
            $marker['en'] = '';
        }
        $marker['b_code'] = $marker['location_id'];
        $marker['l_code'] = $marker['plan_id'];
        $marker['building'] = $marker['location_id'];
        $marker['level'] = $marker['plan_id'];
        $marker['levelData'] = get_level_by_code($marker['building'], $marker['level']);
        $marker['l_code_display'] = $marker['levelData']['l_code_display'];
        $marker['asset_id'] = '';
        $marker['room_no'] = isset($markerData['room']) ? $markerData['room'] : '';
        $marker['primary'] = 'N';
        $marker['label_b_x'] = 0;
        $marker['label_b_y'] = 0;
        return $marker;
    }

    public static function getLandmarkLikeAsset($markerId)
    {
        global $db;
        $q = "SELECT * FROM `markers` WHERE marker_id = $markerId";
        $r = $db->query($q);
        if ($db->affected_rows < 1) return null;
        $row = $r->fetch_assoc();
        $row = static::prepareLikeAsset($row);
        return $row;
    }
}