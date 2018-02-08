<?php

namespace PamCore\Dictionary;

use PamCore\Model;

/**
 * Class Category
 * @package PamCore\Dictionary
 */
class Category extends Model
{
    /**
     * @var string
     */
    protected $tableName = 'dictionary_categories';

    /**
     * @var string
     */
    protected $idColumn = 'cat_id';

    /**
     * @var array
     */
    private static $allItems = [];

    /**
     * Is primary dictionary category.
     * Optimized for processing many queries.
     *
     * @param int $catId
     * @return bool|null
     */
    public function isPrimaryDicCatBulk($catId)
    {
        if (empty(self::$allItems)) {
            self::$allItems = $this->getAll();
        }

        $cat = array_key_exists($catId, self::$allItems) ? self::$allItems[$catId] : null;

        if ($cat) {
            return $cat['cat_type'] === 'Primary';
        }

        return null;
    }
}