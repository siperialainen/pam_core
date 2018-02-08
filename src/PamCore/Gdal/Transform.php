<?php
namespace PamCore\Gdal;
use PamCore\Geo\GroundControlPoint;
use PamCore\Geo\LocationEntity;
use PamCore\Geo\Point;
use PamCore\Log;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Class Transform 
 * Provides interface to 'gdaltransform' command line utility
 * @see http://www.gdal.org/gdaltransform.html
 */
class Transform
{
    const GDAL_TRANSFORM_CLI = 'gdaltransform';

    /**
     * @var Transform
     */
    private static $instance;

    /**
     * @return Transform
     */
    public static function get()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function __construct()
    {}

    /**
     * Performs transformation of image coordinates (x,y) to world coordinates (longitude, latitude)
     * @param GroundControlPoint[] $groundControlPoints
     * @param \PamCore\Geo\Point[] $imagePoints
     * @return LocationEntity[]
     * @throws Exception
     */
    public function transformToGeoCoordinates($groundControlPoints, $imagePoints)
    {
        return $this->transform($groundControlPoints, $imagePoints);
    }

    /**
     * Performs transformation of world coordinates (longitude, latitude) to image coordinates (x,y)
     * @param GroundControlPoint[] $groundControlPoints
     * @param \PamCore\Geo\LocationEntity[] $geoLocations
     * @return Point[]
     * @throws Exception
     */
    public function transformToImageCoordinates($groundControlPoints, $geoLocations)
    {
        return $this->transform($groundControlPoints, null, $geoLocations, true);
    }

    /**
     * Performs transformation of image coordinates (x,y) to world coordinates (longitude, latitude)
     * Also can perform inverse transformation
     * Transformation is based on supplied ground control points
     * @param GroundControlPoint[] $groundControlPoints
     * @param Point[] $imagePoints
     * @param \PamCore\Geo\LocationEntity[] $geoLocations
     * @param bool $inverseTransformation perform inverse transformation: from world coordinates to image coordinates
     * @return \PamCore\Geo\LocationEntity[] | Point[]
     * @throws Exception
     */
    private function transform($groundControlPoints, $imagePoints = [], $geoLocations = [], $inverseTransformation = false) {
        $args = [static::GDAL_TRANSFORM_CLI, '-tps'];
        foreach ($groundControlPoints as $gcPoint) {
            $args  = array_merge($args, [
                '-gcp',
                $gcPoint->point->x,
                $gcPoint->point->y,
                $gcPoint->location->getLongitude(),
                $gcPoint->location->getLatitude(),
            ]);
        }

        $input = '';
        if ($inverseTransformation) {
            $args[] = '-i';
            foreach ($geoLocations as $geoLocation) {
                $input .= "{$geoLocation->getLongitude()} {$geoLocation->getLatitude()}\n";
            }
        } else {
            foreach ($imagePoints as $point) {
                $input .= "{$point->x} {$point->y}\n";
            }
        }

        $processBuilder = new ProcessBuilder($args);
        $process = $processBuilder->setInput($input)->getProcess();
        $exitCode = $process->run();
        $output = $process->getOutput();

        $this->checkProcessResult($exitCode, $output, $process->getErrorOutput(), $inverseTransformation);

        $outputArray = explode("\n", $output);

        $result = [];
        $srcObjects = $inverseTransformation ? $geoLocations : $imagePoints;
        foreach ($srcObjects as $key => $object) {
            $outputString = each($outputArray)[1];
            $exploded = explode(" ", $outputString);
            if (count($exploded) < 2) {
                continue;
            }

            if ($inverseTransformation) {
                list($x, $y) = $exploded;
                $result[$key] = new Point(ceil($x), ceil($y));
            } else {
                list($longitude, $latitude) = $exploded;
                $result[$key] = new LocationEntity([
                    'latitude' => floatval($latitude),
                    'longitude' => floatval($longitude),
                ]);
            }
        }
        return $result;
    }

    /**
     * @param int $exitCode
     * @param string $output
     * @param string $errorOutput
     * @param bool $inverseTransformation
     * @throws Exception
     */
    private function checkProcessResult($exitCode, $output, $errorOutput, $inverseTransformation)
    {
        if ($exitCode == 0) {
            return;
        }
        $cli = static::GDAL_TRANSFORM_CLI;

        if ($inverseTransformation) {
            $errorMessage = 'Unable to transform world coordinates (longitude, latitude) to floorplan image coordinates (x,y)';
        } else {
            $errorMessage = 'Unable to transform floorplan image coordinates (x,y) to world coordinates (longitude, latitude)';
        }

        $message = "{$errorMessage}, {$cli} utility exited with code {$exitCode}, output: {$output}, " .
            " error output: {$errorOutput}";
        Log::get()->addError($message);
        throw new Exception($message);
    }
}

