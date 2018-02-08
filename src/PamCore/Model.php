<?php
namespace PamCore;

use Pam\Entity\Entity;
use PamCore\Db\Utils;

class Model
{
    /**
     * Contains array like ['table1' => ['column1', 'column2'], 'table1' => ['column1', 'column2']]
     * Values are set automatically (if not set) when insert/update function is called
     *
     * @var array
     */
    protected static $tableColumns = [];

    protected $tableName = '';

    protected $idColumn = '';

    /**
     * Contains the name of the table column which allows to distinguish rows of different subclasses
     * @var string
     */
    protected $discriminatorColumn = null;

    /**
     * Contains the value of the discriminator for corresponding subclass
     * @var string
     */
    protected $discriminatorValue = null;

    /**
     * DB connection
     * @var resource
     */
    protected $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    private function readColumns()
    {
        if (array_key_exists($this->tableName, static::$tableColumns)) return;

        $q = "SELECT c.COLUMN_NAME as `column`
FROM information_schema.tables t
JOIN information_schema.columns c ON t.TABLE_NAME = c.TABLE_NAME 
 AND t.TABLE_CATALOG=c.TABLE_CATALOG 
 AND t.TABLE_SCHEMA=c.TABLE_SCHEMA
WHERE t.TABLE_NAME = '{$this->tableName}'";
        $result = mysqli_query($this->db, $q) or die(mysqli_error($this->db));
        static::$tableColumns[$this->tableName] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            static::$tableColumns[$this->tableName][] = $row['column'];
        }
    }

    /**
     * Insert new row to DB filtering $data by $this->column array
     *
     * @param array $data
     * @param bool $onDuplicateUpdate
     * @return int
     * @throws \Exception
     */
    public function insert($data, $onDuplicateUpdate = false)
    {
        $this->readColumns();
        $data = array_intersect_key($data, array_flip($this->getColumns()));

        if ($this->isDiscriminatorColumnExist()) {
            $data[$this->discriminatorColumn] = $this->discriminatorValue;
        }
        $fields = array_keys($data);
        $values = array_values($data);

        $fieldsString = Utils::arrayToColumnsStatement($fields, $this->db);
        $valuesString = Utils::arrayToInStatement($values, $this->db);
        $q = "INSERT INTO `{$this->tableName}` ($fieldsString) VALUES ($valuesString)";
        if ($onDuplicateUpdate) {
            $q .= " ON DUPLICATE KEY UPDATE " . Utils::arrayToSetStatement($data, $this->db);
        }

        $res = mysqli_query($this->db, $q);
        if (!$res) {
            throw new \Exception($q . ' ' . mysqli_error($this->db), mysqli_errno($this->db));
        }
        return mysqli_insert_id($this->db);
    }

    /**
     * Each item must have the same data set
     *
     * @param array[] $data
     * @throws \Exception
     *
     * @return string executed query
     */
    public function bulkInsert($data)
    {
        $item = reset($data);
        if ($item instanceof Entity) {
            $item = $item->getArray();
        }
        $query = "INSERT INTO `{$this->tableName}` " . $this->getInsertColumnsStmt($item) . " VALUES ";
        $insertStmts = [];
        foreach ($data as $item) {
            if ($item instanceof Entity) {
                $item = $item->getArray();
            }
            $insertStmts[] = $this->getInsertValuesStmt($item);
        }
        $query .= implode(',', $insertStmts);

        $res = mysqli_query($this->db, $query);
        if (!$res) {
            throw new \Exception($query . ' ' . mysqli_error($this->db), mysqli_errno($this->db));
        }

        return $query;
    }

    protected function getInsertColumnsStmt($data)
    {
        $this->readColumns();
        $data = array_intersect_key($data, array_flip($this->getColumns()));

        if ($this->isDiscriminatorColumnExist()) {
            $data[$this->discriminatorColumn] = $this->discriminatorValue;
        }
        $fields = array_keys($data);

        return '(' . Utils::arrayToColumnsStatement($fields, $this->db) . ')';
    }

    /**
     * Get values list statement covered by parenthesises
     * Several values can be concatenated with comma to create final bulk insert query
     *
     * @param $data
     * @return string
     */
    protected function getInsertValuesStmt($data)
    {
        $this->readColumns();
        $data = array_intersect_key($data, array_flip($this->getColumns()));

        if ($this->isDiscriminatorColumnExist()) {
            $data[$this->discriminatorColumn] = $this->discriminatorValue;
        }
        $values = array_values($data);

        return '(' . Utils::arrayToInStatement($values, $this->db) . ')';
    }

    /**
     * @param mixed $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function update($id, $data)
    {
        $this->readColumns();
        $id = mysqli_real_escape_string($this->db, $id);
        $data = array_intersect_key($data, array_flip($this->getColumns()));
        unset($data[$this->idColumn]);
        $discriminatorWhere = $this->getDiscriminatorWhereStatement();
        $q = "UPDATE `{$this->tableName}` SET " . Utils::arrayToSetStatement($data, $this->db)
            . " WHERE `{$this->idColumn}` = '{$id}' $discriminatorWhere";
        $res = mysqli_query($this->db, $q);
        if (!$res) {
            throw new \Exception($q . ' ' . mysqli_error($this->db), mysqli_errno($this->db));
        }
        return true;
    }

    /**
     * @param array $whereFields
     * @param array $data
     * @return bool|\mysqli_result
     * @throws \Exception
     */
    public function updateWhere(array $whereFields, array $data)
    {
        $set = Utils::arrayToSetStatement($data, $this->db);
        $where = $this->getWhereForFields($whereFields);
        $q = "UPDATE `{$this->tableName}` SET {$set} WHERE {$where}";
        $res = $this->db->query($q);
        if (!$res) {
            throw new \Exception($q . ' ' . mysqli_error($this->db), mysqli_errno($this->db));
        }
        return $res;
    }

    /**
     * @param $id
     * @throws \Exception
     */
    public function delete($id)
    {
        $id = mysqli_real_escape_string($this->db, $id);
        $discriminatorWhere = $this->getDiscriminatorWhereStatement();
        $this->deleteWhere("`{$this->idColumn}` = '{$id}' {$discriminatorWhere}");
    }

    protected function deleteWhere($where)
    {
        $q = "DELETE FROM `{$this->tableName}` WHERE $where";
        $res = mysqli_query($this->db, $q);
        if (!$res) {
            throw new \Exception(mysqli_error($this->db), mysqli_errno($this->db));
        }
    }

    /**
     * @param $id
     * @return array row with specified id
     * @throws \Exception
     */
    public function getOne($id)
    {
        $id = $this->db->real_escape_string($id);
        $q = $this->makeQuery("`{$this->idColumn}` = '$id'", null, 1);
        return $this->fetchRow($q);
    }

    /**
     * @param array $fields
     * @return mixed|null
     */
    public function getOneByFields($fields)
    {
        $where = $this->getWhereForFields($fields);
        $q = $this->makeQuery($where, null, 1);
        return $this->fetchRow($q);
    }

    /**
     * @param array $orderBy array like ['col1', 'col2', ...] or ['col1' => 'DESC', 'col2' => 'ASC', ...]
     * @param string $keyColumn Which column should be used to put as the key of result array
     * @return array
     */
    public function getAll($orderBy = [], $keyColumn = null)
    {
        $q = $this->makeQuery(null, $orderBy);
        return $this->fetchRows($q, null, $keyColumn);
    }

    /**
     * @param array $fields
     * @return array
     */
    public function getAllByFields($fields)
    {
        return $this->getAllWhere($this->getWhereForFields($fields));
    }

    /**
     * Returns all by WHERE condition.
     *
     * @param string $whereCondition Like 'id = 23'
     * @return array
     * @throws \Exception
     */
    public function getAllWhere($whereCondition)
    {
        $q = $this->makeQuery($whereCondition);
        return $this->fetchRows($q);
    }

    /**
     * @param string $where
     * @param array $order
     * @param string $limit
     * @param array $columns
     * @return string
     */
    protected function makeQuery($where = null, $order = null, $limit = null, $columns = null)
    {
        $discriminatorWhere = $this->getDiscriminatorWhereStatement(false);
        $wheres = array_filter([$where, $discriminatorWhere]);

        $whereStatement = count($wheres) ? ('WHERE (' . implode(') AND (', $wheres) . ')') : '';
        $orderStatement = Utils::arrayToOrderByStatement($order);
        $limitStatement = !is_null($limit) ? "LIMIT $limit" : '';
        $columnsStatement = Utils::arrayToColumnsStatement(empty($columns) ? $this->getColumns() : $columns, $this->db);

        return "SELECT {$columnsStatement} FROM `{$this->tableName}` $whereStatement $orderStatement $limitStatement";
    }

    protected function fetchRows($q, $limit = null, $keyColumn = null)
    {
        $res = mysqli_query($this->db, $q);
        if (!$res) {
            throw new \Exception(mysqli_error($this->db), mysqli_errno($this->db));
        }
        $rows = [];
        $counter = 0;
        if ($keyColumn === null) {
            $keyColumn = $this->idColumn;
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[$row[$keyColumn]] = $row;
            $counter++;
            if (!is_null($limit) && $counter >= $limit) {
                break;
            }
        }
        return $rows;
    }

    protected function fetchRow($q)
    {
        $rows = $this->fetchRows($q, 1);
        if (!count($rows)) {
            return null;
        }

        return reset($rows);
    }

    /**
     * @return bool
     */
    private function isDiscriminatorColumnExist()
    {
        return !is_null($this->discriminatorColumn) && !is_null($this->discriminatorValue);
    }

    /**
     * @param bool $withANDcondition
     * @return string
     */
    protected function getDiscriminatorWhereStatement($withANDcondition = true)
    {
        return $this->isDiscriminatorColumnExist()
            ? ($withANDcondition ? "AND " : "") . "`{$this->discriminatorColumn}` = '{$this->discriminatorValue}'"
            : '';
    }

    /**
     * @return string[]
     */
    protected function getColumns()
    {
        $this->readColumns();
        return static::$tableColumns[$this->tableName];
    }

    protected function getWhereForFields($fields)
    {
        $where = [];
        foreach ($fields as $name => $value) {
            if (is_null($value)) {
                $where[] = sprintf("`%s` IS NULL", $name);
            } else if ($value === true) {
                $where[] = sprintf("`%s` = TRUE", $name);
            } else if ($value === false) {
                $where[] = sprintf("`%s` = FALSE", $name);
            } else if (is_array($value)) {
                $where[] = sprintf("`%s` IN(" . Utils::arrayToInStatement($value, $this->db) . ")", $name);
            } else {
                $where[] = sprintf("`%s` = '%s'", $name, $this->db->real_escape_string($value));
            }
        }

        return implode(' AND ', $where);
    }

    public function getIdColumn()
    {
        return $this->idColumn;
    }
}