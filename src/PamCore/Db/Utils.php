<?php
namespace PamCore\Db;

class Utils
{
    /**
     * Prepare SQL by term search string
     * Example:
     *    $string = 'tag1,tag2'
     *    $termsDivider = ','
     *    $template = 'asset_tag.name="%{search]%" OR asset_type_tag.name="%{value}%"'
     * result: '(asset_tag.name="%tag1%" OR asset_type_tag.name="%tag1%") OR (asset_tag.name="%tag2%" OR asset_type_tag.name="%tag2%")'
     * return '1' string if no terms found in search string
     *
     * @param $search
     * @param $termsDivider
     * @param $template
     * @param $db
     * @param string $tokenTemplate
     * @return string
     */
    public static function searchTermString2SqlByTemplate($search, $termsDivider, $template, $token, $db, $tokenTemplate = '{%s}')
    {
        $searchTerms = array_filter(
            array_map(
                function($value){
                    return trim($value);
                },
                explode($termsDivider, $search)
            ),
            function($value) {
                return !empty($value);
            }
        );

        $ret = array_map(function($value) use ($template, $token, $db, $tokenTemplate) {
            return static::processSqlTemplate([$token => $value], $template, $db, $tokenTemplate);
        }, $searchTerms);

        return count($ret) ? '(' . implode(') OR (', $ret) . ')' : '1';
    }

    /**
     * Prepare SQL string by template
     * Example:
     *     $values = ['value' => 'string1', 'id' => 123];
     *     $pattern = 'WHERE value="%{value}%" AND id IN (%id%)';
     *     result: 'WHERE value="%string1%" AND id IN (%123%)'
     * @param array $values
     * @param string $template
     * @param null $db
     * @param string $tokenTemplate
     * @return string
     */
    public static function processSqlTemplate($values, $template, $db = null, $tokenTemplate = '{%s}')
    {
        $search = array_map(function($name) use ($tokenTemplate) {
            return sprintf($tokenTemplate, $name);
        }, array_keys($values));
        $replace = array_map(function($value) use ($db) {
            return $db ? mysqli_real_escape_string($db, $value) : $value;

        }, array_values($values));
        return str_replace($search, $replace, $template);
    }

    /**
     * Converts array of values into string which can be used in SQL 'WHERE IN' statement:
     *  ['value1', 'value2', 'value3'] converts into string with properly escaped values: "'value1', 'value2', 'value3'"
     * @param array $values
     * @param $db
     * @return string
     */
    public static function arrayToInStatement($values, $db = null) {
        $escapedValues = array_map(function($value) use ($db) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if ($value === null) {
                return 'NULL';
            }
            return "'" . (is_null($db) ? $value : mysqli_real_escape_string($db, $value)) . "'";
        }, $values);
        return implode(", ", $escapedValues);
    }

    /**
     * Converts array of columns names into string which can be used in SQL:
     *  ['column1', 'column2', 'column3'] converts into string with properly escaped values: "`column1`, `column2`, `column3`"
     * 
     * @param array $columns
     * @param $db
     * @return string
     */
    public static function arrayToColumnsStatement($columns, $db) {
        $escapedColumns = array_map(function($column) use ($db) {
            return mysqli_real_escape_string($db, $column);
        }, $columns);
        return "`" . implode("`,`", $escapedColumns) . "`";
    }

    /**
     * Converts associated array into SET statement:
     * ['column1' => 'value1', 'column2' => 'value2'] into escaped string "`column1`='value1',`column2`='value2'"
     * 
     * @param array $data
     * @param \mysqli $db
     * @return string
     */
    public static function arrayToSetStatement($data, $db) {
        $preparedData = array_map(function($key, $value) use ($db) {
            if ($value === null) {
                return "`" . mysqli_real_escape_string($db, $key) . "`=NULL";
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }
            return "`" . mysqli_real_escape_string($db, $key). "`='" . mysqli_real_escape_string($db, $value) . "'";
        }, array_keys($data), array_values($data));
        return implode(",", $preparedData);
    }

    /**
     * Converts columns array to 'ORDER BY' statement
     * @param array $orderBy array like ['col1', 'col2', ...] or ['col1' => 'DESC', 'col2' => 'ASC', ...]
     * @return string
     */
    public static function arrayToOrderByStatement($orderBy) {
        $result = '';
        if (!empty($orderBy)) {
            $strOrderBy = [];
            foreach ($orderBy as $key => $value) {
                if (is_int($key)) {
                    $strOrderBy[] = "`$value`";
                } else {
                    $strOrderBy[] = "`$key` $value";
                }
            }
            $result = " ORDER BY " . implode(', ', $strOrderBy);
        }
        return $result;
    }

    /**
     * @param string $auditLevelsOrder raw column value
     * @return array
     */
    public static function prepareAuditLevelsOrderArray($auditLevelsOrder) {
        $auditLevelsOrder = json_decode($auditLevelsOrder, true);
        if (!is_array($auditLevelsOrder)) {
            $auditLevelsOrder = [];
        }
        $tempArray = [];
        foreach($auditLevelsOrder as $building) {
            $k = key($building);
            $tempArray[$k] = $building[$k];
        }
        return $tempArray;
    }
}