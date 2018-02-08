<?php
namespace PamCore;

use PamCore\Model;

class UrlShortener extends Model
{
    /**
     * @var string
     */
    private $alphabet = '6A3YmaOzkFhfI1gt9LQ4KvC7rxyHRdEVq5TUoDbnWujsNPeG2ZpXSBilM08wcJ';

    /**
     * @var string
     */
    private $entryPoint;

    /**
     * @var string
     */
    protected $tableName = 'url_shortener';

    /**
     * @var string
     */
    protected $idColumn = 'id';

    /**
     * UrlShortener constructor.
     * @param string $entryPoint
     */
    public function __construct($entryPoint)
    {
        parent::__construct();
        $this->entryPoint = $entryPoint;
    }

    /**
     * @param int $id
     * @return string
     */
    private function encode($id)
    {
        $base = strlen($this->alphabet);
        $hash = '';
        do {
            $m = (int) floor($id % $base);
            $hash = $this->alphabet[$m] . $hash;
            $id = (int) floor(($id - $m) / $base);
        } while ($id > 0);

        return $hash;
    }

    /**
     * @param string $url
     * @return null|string
     */
    public function shorten($url)
    {
        $entity = $this->getOneByFields([
            'url' => $url,
        ]);

        if (null !== $entity && isset($entity['hash'])) {
            return $this->entryPoint . '/' .$entity['hash'];
        }

        $id = $this->insert([
            'url' => $url,
        ], true);

        $hash = $this->encode($id);
        $this->update($id, [
            'hash' => $hash
        ]);

        return $this->entryPoint . '/' . $hash;
    }

    /**
     * @param string $hash
     * @return string|null
     */
    public function expand($hash)
    {
        $entity = $this->getOneByFields([
            'hash' => $hash,
        ]);

        if (null !== $entity && isset($entity['url'])) {
            return $entity['url'];
        }

        return null;
    }
}