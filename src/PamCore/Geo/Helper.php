<?php
namespace PamCore\Geo;

use PamCore\Gdal\Coordinates;
use PamCore\Gdal\Transform;
use PamCore\Log;

class Helper
{
    const ASSET = 'asset';
    const MARKER = 'marker';

    const DEFAULT_ASSET_DISPLAY_SIZE = 80;
    const DEFAULT_ASSET_WIDTH = 400;
    const ASSET_SVG_CANVAS_SIZE = 1300;

    /**
     * @var Helper
     */
    private static $instance;

    /**
     * DB connection
     * @var resource
     */
    private $db;

    /**
     * @return Helper
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * @param GroundControlPoint[] $groundControlPoints
     * @param string $locationType 'level' | 'external'
     * @param int $locationId
     * @return \string[]
     * @throws Exception
     */
    public function geoLocateMarkers($groundControlPoints, $locationType, $locationId = null)
    {
        if ($locationType == 'level') {
            $locationIdCondition = is_null($locationId) ? '' : " AND l.l_id = {$locationId}";
            $selectQuery = "SELECT m.marker_id as id, m.location_x, m.location_y, m.geoLocationId, l.l_id location_id FROM markers m 
JOIN site_levels l ON m.plan_id = l.l_code 
JOIN site_buildings b ON l.b_id = b.b_id 
LEFT JOIN geo_location loc ON loc.id = m.geoLocationId
WHERE b.b_code = m.location_id {$locationIdCondition}";
        } elseif ($locationType == 'external') {
            $locationIdCondition = is_null($locationId) ? '' : " AND e.ext_id = {$locationId}";
            $selectQuery = "SELECT m.marker_id as id, m.location_x, m.location_y, m.geoLocationId, e.ext_id location_id FROM markers m 
JOIN site_external e ON m.location_id = e.ext_id 
LEFT JOIN geo_location loc ON loc.id = m.geoLocationId
WHERE e.ext_id = m.location_id {$locationIdCondition}";
        } else {
            $message = 'Unsupported location type specified: ' . $locationType;
            Log::get()->addError($message);
            throw new Exception($message);
        }

        return $this->geoLocateObjects('marker', $selectQuery, $groundControlPoints, $locationType, $locationId);
    }

    /**
     * @param GroundControlPoint[] $groundControlPoints
     * @param string $locationType 'level' | 'external'
     * @param int $levelId
     * @return string[]
     */
    public function geoLocateAssets($groundControlPoints, $locationType, $levelId = null)
    {
        if ($locationType == 'level') {
            $levelIdCondition = is_null($levelId) ? '' : " AND l.l_id = {$levelId}";
            $selectQuery = "SELECT a.asset_id as id, a.location_x, a.location_y, a.location_rot, a.geoLocationId, t.width, l.l_id location_id FROM assets a 
JOIN asset_types t ON t.asset_type_id = a.asset_type_id
JOIN site_levels l ON a.level = l.l_code 
JOIN site_buildings b ON l.b_id = b.b_id 
LEFT JOIN geo_location loc ON loc.id = a.geoLocationId
WHERE b.b_code = a.building {$levelIdCondition}";
        } elseif ($locationType == 'external') {
            $levelIdCondition = is_null($levelId) ? '' : " AND e.ext_id = {$levelId}";
            $selectQuery = "SELECT a.asset_id as id, a.location_x, a.location_y, a.location_rot, a.geoLocationId, t.width, e.ext_id location_id FROM assets a 
JOIN asset_types t ON t.asset_type_id = a.asset_type_id
JOIN site_external e ON a.ext_id = e.ext_id  
LEFT JOIN geo_location loc ON loc.id = a.geoLocationId
WHERE e.ext_id = a.ext_id {$levelIdCondition}";
        } else {
            $message = 'Unsupported location type specified: ' . $locationType;
            Log::get()->addError($message);
            throw new Exception($message);
        }
        return $this->geoLocateObjects('asset', $selectQuery, $groundControlPoints, $locationType, $levelId);
    }

    /**
     * @param string $objectType
     * @param string $selectQuery
     * @param GroundControlPoint[] $groundControlPoints
     * @param string $locationType 'level' | 'external'
     * @param int $levelId
     * @return \string[]
     */
    private function geoLocateObjects($objectType, $selectQuery, $groundControlPoints, $locationType = null, $levelId = null)
    {
        global $db;

        $result = mysqli_query($db, $selectQuery) or die(mysqli_error($db));
        $objects = [];

        if (!is_null($locationType) && !is_null($levelId)) {
            $groundControlPoints[$levelId] = $groundControlPoints;
        }

        while ($object = mysqli_fetch_assoc($result)) {
            if (array_key_exists($object['location_id'], $groundControlPoints)) {
                if (!array_key_exists($object['location_id'], $objects)) {
                    $objects[$object['location_id']] = [];
                }
                $objects[$object['location_id']][$object['id']] = $object;
            }
        };

        //calculate assets/markers geo locations by ground control points
        $objectsGeoLocations = [];
        /** @var LocationEntity[][] $vertexGeoLocations asset vertex locations in pixel coordinates*/
        $vertexGeoLocations = [];
        foreach ($objects as $levId => $levelAssets) {
            $objectLocations = array_map(function ($object) {
                return new Point($object['location_x'], $object['location_y']);
            }, $levelAssets);

            $vertexLocations = [];
            if ($objectType == 'asset') {
                $vertexLocations = array_map(function ($object) {
                    return $this->getAssetVertexLocation(
                        $object['location_x'],
                        $object['location_y'],
                        $object['location_rot'],
                        $object['width'] ? $object['width'] : 1
                    );
                }, $levelAssets);
            }

            if (array_key_exists($levId, $groundControlPoints)) {
                $levelGCPoints = $groundControlPoints[$levId];
                $objectsGeoLocations[$levId] = Transform::get()->transformToGeoCoordinates($levelGCPoints, $objectLocations);
                if ($objectType == 'asset') {
                    $vertexGeoLocations[$levId] = Transform::get()->transformToGeoCoordinates($levelGCPoints, $vertexLocations);
                }
            }
        }

        //clean up existing geo locations for assets/markers
        $this->cleanUpGeoLocations($objectType, $locationType, $levelId);

        //save assets/markers geo locations
        $result = [];
        foreach ($objectsGeoLocations as $levId => $objectLevelLocations) {
            foreach ($objectLevelLocations as $objectId => $geoLocation) {
                /** @var LocationEntity $geoLocation */
                $geoLocation->save();
                $geoLocationId = mysqli_real_escape_string($db, $geoLocation->id);
                $objectId = mysqli_real_escape_string($db, $objectId);

                $geoRotationUpdate = '';
                $geoRotationMessage = '';
                if ($objectType == 'asset') {
                    $tableName = 'assets';
                    $idColumn = 'asset_id';
                    $vertexGeoLocation = $vertexGeoLocations[$levId][$objectId];

                    $geoRotation = $this->calculateAssetVertexGeoRotation($vertexGeoLocation, $geoLocation);

                    $geoRotationUpdate = ", `geoRotation` = {$geoRotation}";
                    $geoRotationMessage = ", `rotation` = {$geoRotation}";
                } else {
                    $tableName = 'markers';
                    $idColumn = 'marker_id';
                }

                $updateMarkersQuery = "UPDATE {$tableName} set `geoLocationId` = {$geoLocationId} {$geoRotationUpdate} WHERE {$idColumn} = {$objectId}";
                mysqli_query($db, $updateMarkersQuery) or die(mysqli_error($db));

                $result[] = "{$objectType} {$objectId} was geo located with `latitude` = {$geoLocation->getLatitude()}, `longitude` = {$geoLocation->getLongitude()}{$geoRotationMessage}\n";
            }
        }
        return $result;
    }

    /**
     * Cleans up existing geo locations for assets/markers
     * @param string $objectType
     * @param string $locationType 'level' | 'external'
     * @param int $locationId
     */
    private function cleanUpGeoLocations($objectType, $locationType = null, $locationId = null)
    {
        if ($objectType == 'asset') {
            if ($locationType == 'level') {
                $whereCondition = "JOIN `site_buildings` b ON b.b_code = `assets`.`building` JOIN `site_levels` l ON l.`b_id` = b.`b_id`";
                if (!is_null($locationId)) {
                    $whereCondition .= " WHERE l.`l_id` = {$locationId}";
                } else {
                    $whereCondition .= " WHERE l.`l_id` > 0";
                }
            } elseif ($locationType == 'external') {
                if (!is_null($locationId)) {
                    $whereCondition = "JOIN `site_external` e ON e.`ext_id` = `assets`.`ext_id` WHERE e.`ext_id` = {$locationId}";
                } else {
                    $whereCondition = "JOIN `site_external` e ON e.`ext_id` = `assets`.`ext_id` WHERE e.`ext_id` > 0";
                }
            } else {
                $message = 'Unsupported location type specified: ' . $locationType;
                Log::get()->addError($message);
                throw new Exception($message);
            }

            (new LocationModel())->deleteByAssets($whereCondition);
        } else {
            if ($locationType == 'level') {
                $whereCondition = "JOIN `site_buildings` b ON b.b_code = `markers`.`location_id` JOIN `site_levels` l ON l.`b_id` = b.`b_id`";
                if (!is_null($locationId)) {
                    $whereCondition .= " WHERE l.`l_id` = {$locationId}";
                } else {
                    $whereCondition .= " WHERE l.`l_id` > 0";
                }
            } elseif ($locationType == 'external') {
                if (!is_null($locationId)) {
                    $whereCondition = "JOIN `site_external` l ON e.`ext_id` = `markers`.`plan_id` WHERE e.`ext_id` = {$locationId}";
                } else {
                    $whereCondition = "JOIN `site_external` l ON e.`ext_id` = `markers`.`plan_id` WHERE e.`ext_id` > 0";
                }
            } else {
                $message = 'Unsupported location type specified: ' . $locationType;
                Log::get()->addError($message);
                throw new Exception($message);
            }
            (new LocationModel())->deleteByMarkers($whereCondition);
        }
    }

    /**
     * @param int $levelId
     * @param int $x
     * @param int $y
     * @return LocationEntity
     */
    public function geoLocateObjectOnLevel($levelId, $x, $y)
    {
        $gcPoints = GroundControlPoint::findByLevelId($levelId);
        return $this->geoLocateObjectsByGcp($gcPoints, [new Point($x, $y)]);
    }

    /**
     * @param int $externalId
     * @param int $x
     * @param int $y
     * @return LocationEntity
     */
    public function geoLocateObjectOnExternal($externalId, $x, $y)
    {
        $gcPoints = GroundControlPoint::findByExternalId($externalId);
        return $this->geoLocateObjectsByGcp($gcPoints, [new Point($x, $y)]);
    }

    /**
     * @param $levelId
     * @param LocationEntity $geoLocation
     * @return Point|null
     */
    public function imageLocateObjectOnLevel($levelId, LocationEntity $geoLocation)
    {
        $gcPoints = GroundControlPoint::findByLevelId($levelId);
        return $this->imageLocateObject($gcPoints, $geoLocation);
    }

    /**
     * @param $externalId
     * @param LocationEntity $geoLocation
     * @return Point|null
     */
    public function imageLocateObjectOnExternal($externalId, LocationEntity $geoLocation)
    {
        $gcPoints = GroundControlPoint::findByExternalId($externalId);
        return $this->imageLocateObject($gcPoints, $geoLocation);
    }

    /**
     * @param GroundControlPoint[] $gcPoints
     * @param LocationEntity $geoLocation
     * @return Point|null
     */
    public function imageLocateObject($gcPoints, LocationEntity $geoLocation)
    {
        if (count($gcPoints) == 0) {
            return null;
        }
        $imagePoints = Transform::get()->transformToImageCoordinates($gcPoints, [$geoLocation]);
        return reset($imagePoints);
    }

    /**
     * @param GroundControlPoint[] $gcPoints
     * @param Point[] $points
     * @return LocationEntity|null
     */
    private function geoLocateObjectsByGcp($gcPoints, $points)
    {
        if (count($gcPoints) == 0) {
            return null;
        }
        $geoLocations = Transform::get()->transformToGeoCoordinates($gcPoints, $points);
        return reset($geoLocations);
    }

    /**
     * @param int $levelId
     * @param int $x
     * @param int $y
     * @param int $rotation
     * @param int $width
     * @param LocationEntity $assetGeoLocation
     * @return float|null
     */
    public function geoRotateAssetOnLevel($levelId, $x, $y, $rotation, $width, LocationEntity $assetGeoLocation)
    {
        $gcPoints = GroundControlPoint::findByLevelId($levelId);
        return $this->geoRotateAsset($gcPoints, $x, $y, $rotation, $width, $assetGeoLocation);
    }

    /**
     * @param int $externalId
     * @param int $x
     * @param int $y
     * @param int $rotation
     * @param int $width
     * @param LocationEntity $assetGeoLocation
     * @return float|null
     */
    public function geoRotateAssetOnExternal($externalId, $x, $y, $rotation, $width, LocationEntity $assetGeoLocation)
    {
        $gcPoints = GroundControlPoint::findByExternalId($externalId);
        return $this->geoRotateAsset($gcPoints, $x, $y, $rotation, $width, $assetGeoLocation);
    }

    /**
     * @param GroundControlPoint[] $gcPoints
     * @param int $x
     * @param int $y
     * @param int $rotation
     * @param int $width
     * @param LocationEntity $assetGeoLocation
     * @return float|null
     */
    private function geoRotateAsset($gcPoints, $x, $y, $rotation, $width, LocationEntity $assetGeoLocation)
    {
        if (count($gcPoints) == 0) {
            return null;
        }
        $vertexLocation = $this->getAssetVertexLocation($x, $y, $rotation, $width);
        $vertexGeoLocations = Transform::get()->transformToGeoCoordinates($gcPoints, [$vertexLocation]);
        $vertexGeoLocation = reset($vertexGeoLocations);
        return $this->calculateAssetVertexGeoRotation($vertexGeoLocation, $assetGeoLocation);
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $rotation
     * @param int $width
     * @return Point
     */
    private function getAssetVertexLocation($x, $y, $rotation, $width)
    {
        $pixelWidth = $width * static::DEFAULT_ASSET_DISPLAY_SIZE / static::ASSET_SVG_CANVAS_SIZE;
        $halfWidth = $pixelWidth / 2;
        $vertexX = $halfWidth * cos(deg2rad($rotation)) + $x;
        $vertexY = $halfWidth * sin(deg2rad($rotation)) + $y;
        return new Point($vertexX, $vertexY);
    }

    /**
     * @param LocationEntity $vertexGeoLocation
     * @param LocationEntity $assetGeoLocation
     * @return float|int
     */
    private function calculateAssetVertexGeoRotation($vertexGeoLocation, $assetGeoLocation)
    {
        $r = sqrt(pow($vertexGeoLocation->getLongitude() - $assetGeoLocation->getLongitude(), 2) + pow($vertexGeoLocation->getLatitude() - $assetGeoLocation->getLatitude(), 2));
        $acosArg = ($vertexGeoLocation->getLongitude() - $assetGeoLocation->getLongitude()) / $r;
        $geoRotation = rad2deg(acos($acosArg));

        $yDiff = ($vertexGeoLocation->getLatitude() - $assetGeoLocation->getLatitude());
        if ($yDiff > 0) {
            $geoRotation = 360 - $geoRotation;
            return $geoRotation;
        }
        return $geoRotation;
    }

    /**
     * @param int $levelId
     * @param int $externalId
     * @param string $filePath
     * @return GroundControlPoint[]
     */
    public function setGroundControlPointsFromFile($filePath, $levelId = null, $externalId = null)
    {
        $gcPoints = $this->parsePointsFile($filePath, $levelId, $externalId);
        if ($levelId) {
            GroundControlPoint::deleteByLevelId($levelId);
        }
        if ($externalId) {
            GroundControlPoint::deleteByExternalId($externalId);
        }

        foreach ($gcPoints as $gcPoint) {
            $gcPoint->save();
        }
        return $gcPoints;
    }

    /**
     * @param string $filePath
     * @param $levelId
     * @param $externalId
     * @return GroundControlPoint[]
     */
    private function parsePointsFile($filePath, $levelId = null, $externalId = null)
    {
        //parse '*.points' CSV file, which has the following format:
        //mapX,mapY,pixelX,pixelY,enable
        //16831320.2052624449133873,-4013170.47320769866928458,1065.31445783132403449,-371.98554216867472633,1
        $csv = new \parseCSV($filePath);
        $this->checkCsvParseError($csv);
        $this->checkGroundControlPointsNumber($csv);

        $gcPoints = [];

        $sourceCoords = [];
        foreach ($csv->data as $data) {
            $this->checkGroundControlPointRow($data);
            $sourceCoords[] = new Point($data['mapX'], $data['mapY']);
        }

        //Convert GCP coordinates from EPGS 3857 (Pseudo Mercator) to EPGS 4326 (latitude/longitude) coordinate reference system
        $destCoords = Coordinates::get()->transform($sourceCoords);

        foreach ($csv->data as $key => $data) {
            $gcPoints[] = new GroundControlPoint($data['pixelX'], -$data['pixelY'], $destCoords[$key]->y, $destCoords[$key]->x, $levelId, $externalId);
        }

        return $gcPoints;
    }

    /**
     * @param \parseCSV $csv
     * @throws Exception
     */
    private function checkCsvParseError(\parseCSV $csv)
    {
        if ($csv->error == 0) {
            return;
        }
        $errorMessage = 'The following error appeared during the processing of ground control points file, please check the file contains data in CSV format: ';
        $errorInfo = reset($csv->error_info);
        if (!empty($errorInfo) && isset($errorInfo['info'])) {
            $errorMessage .= $errorInfo['info'];
        } else {
            $errorMessage .= 'unknown error';
        }
        Log::get()->addError($errorMessage);
        throw new Exception($errorMessage);
    }

    /**
     * @param \parseCSV $csv
     * @throws Exception
     */
    private function checkGroundControlPointsNumber(\parseCSV $csv)
    {
        $pointsNumber = count($csv->data);
        if ($pointsNumber < 3) {
            $errorMessage = "Ground control points file should contain at least 3 ground control points, only {$pointsNumber} points found";
            Log::get()->addError($errorMessage);
            throw new Exception($errorMessage);
        }
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function checkGroundControlPointRow($data) {
        $expectedFields = [
            'mapX',
            'mapY',
            'pixelX',
            'pixelY',
        ];
        foreach ($expectedFields as $field) {
            if (!isset($data[$field])) {
                $message = "Ground control points file contains invalid record, " .
                    "please check the header row is present in the file and contains the following column names: " .
                    implode(', ', $expectedFields) . ". " .
                    "Also check all rows in the file contains number of columns which correspond to the header row";
                Log::get()->addError($message);
                throw new Exception($message);
            }
        }
    }

    /**
     * @param array $objects assets or markers DB rows
     * @param string $objectType
     * @return string
     */
    public function prepareGeoLocationsJson($objects, $objectType) {
        $objectsData = [];
        foreach ($objects as $object) {
            if (is_null($object['latitude']) || is_null($object['longitude'])) {
                continue;
            }
            $objectData = [
                'latitude' => floatval($object['latitude']),
                'longitude' => floatval($object['longitude']),
            ];
            if ($objectType == static::ASSET) {
                $objectData['displaySize'] = $this->getAssetDisplayWidth($object['asset_type_id']);
                $objectData['id'] = $object['asset_id'];
                foreach (['building', 'level', 'location'] as $key) {
                    $objectData[$key] = $object[$key];
                }
                $objectData['code'] = $object['asset_code_pre'] . ' ' . $object['asset_code_suf'];
                $objectData['display'] = $object['display'];
                $objectData['phaseId'] = substr($object['status_code'], 0, 2);
            } else {
                $objectData['id'] = $object['marker_id'];
            }
            $objectsData[$objectData['id']] = $objectData ;
        }
        return json_encode($objectsData, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT);
    }

    /**
     * @param int $assetTypeId
     * @return float
     */
    public function getAssetDisplayWidth($assetTypeId)
    {
        $assetTemplate = get_asset_type_first_template($assetTypeId);
        $width = empty($assetTemplate) ? 0 : $assetTemplate['width'];

        //see site/html/img/svg-code/asset_icon.php
        if ($width == 0) {
            $width = static::DEFAULT_ASSET_WIDTH;
        }
        if ($width > static::ASSET_SVG_CANVAS_SIZE) {
            $scale = static::ASSET_SVG_CANVAS_SIZE / $width;
        } else {
            $scale = 1;
        }

        return ceil(static::DEFAULT_ASSET_DISPLAY_SIZE / $scale);
    }

    public function getRotationByDiffWithGeo($rot, $geoRot) {
        $diff = $geoRot - $rot;
        if ($diff > 180) {
            $diff = 360 - $diff;
            $diff = $diff % 360;
            $rot += $diff;
        } else {
            $rot -= $diff;
        }

        if ($rot < 0) $rot += 360;
        return $rot;
    }
}