<?php

namespace Ornament\Adapter;

use Ornament\Adapter;
use Ornament\Container;
use PDO as Base;
use PDOException;

/**
 * Ornament adapter for PDO data sources.
 */
final class Pdo implements Adapter
{
    use Defaults;

    /** @var Private statement cache. */
    private $statements = [];

    /**
     * Constructor. Pass in the adapter (instanceof PDO) as an argument.
     *
     * @return void
     */
    public function __construct(Base $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Query $object (a model) using $parameters with optional $opts.
     *
     * @param object $object A model object.
     * @param array $parameters Key/value pair or WHERE statements, e.g.
     *  ['id' => 1].
     * @param array $opts Hash of options. Supported keys are 'limit',
     *  'offset' and 'order' and they correspond to their SQL equivalents.
     * @return array|false An array of objects of the same class as $object, or
     *  false on query failure.
     */
    public function query($object, array $parameters, array $opts = [])
    {
        $keys = [];
        $values = [];
        foreach ($parameters as $key => $value) {
            $keys[$key] = sprintf('%s = ?', $key);
            $values[] = $value;
        }
        if ($keys) {
            $sql = "SELECT * FROM %1\$s WHERE %2\$s";
            $sql = sprintf(
                $sql,
                $this->identifier,
                implode(' AND ', $keys)
            );
        } else {
            $sql = "SELECT * FROM {$this->identifier}";
        }
        if (isset($opts['order'])) {
            $sql .= sprintf(
                ' ORDER BY %s',
                preg_replace('@[^\w,\s\(\)]', '', $opts['order'])
            );
        }
        if (isset($opts['limit'])) {
            $sql .= sprintf(' LIMIT %d', $opts['limit']);
        }
        if (isset($opts['offset'])) {
            $sql .= sprintf(' OFFSET %d', $opts['offset']);
        }
        $stmt = $this->getStatement($sql);
        $stmt->execute($values);
        return $stmt->fetchAll(Base::FETCH_CLASS, get_class($object));
    }

    /**
     * Load data into a single model.
     *
     * @param object $model The original model.
     * @param Container $object A container object.
     * @return void
     * @throws Ornament\PrimaryKeyException if no primary key was set or could
     *  be determined, and loading would inevitably fail.
     */
    public function load($model, Container $object)
    {
        $pks = [];
        $values = [];
        foreach ($this->primaryKey as $key) {
            if (isset($object->$key)) {
                $pks[$key] = sprintf('%s = ?', $key);
                $values[] = $object->$key;
            } else {
                throw new PrimaryKeyException($model);
            }
        }
        $sql = "SELECT * FROM %1\$s WHERE %2\$s";
        $stmt = $this->getStatement(sprintf(
            $sql,
            $this->identifier,
            implode(' AND ', $pks)
        ));
        $stmt->setFetchMode(Base::FETCH_INTO, $object);
        $stmt->execute($values);
        $stmt->fetch();
        $object->markClean();
    }

    /**
     * Private helper to either get or create a PDOStatement.
     *
     * @param string $sql SQL to prepare the statement with.
     * @return PDOStatement A PDOStatement.
     */
    private function getStatement($sql)
    {
        if (!isset($this->statements[$sql])) {
            $this->statements[$sql] = $this->adapter->prepare($sql);
        }
        return $this->statements[$sql];
    }

    /**
     * Persist the newly created Container $object.
     *
     * @param Ornament\Container $object The model to persist.
     * @return boolean True on success, else false.
     */
    public function create(Container $object)
    {
        $sql = "INSERT INTO %1\$s (%2\$s) VALUES (%3\$s)";
        $placeholders = [];
        $values = [];
        foreach ($this->fields as $field) {
            if (isset($object->$field)) {
                $placeholders[$field] = '?';
                $values[] = $object->$field;
            }
        }
        $sql = sprintf(
            $sql,
            $this->identifier,
            implode(', ', array_keys($placeholders)),
            implode(', ', $placeholders)
        );
        $stmt = $this->getStatement($sql);
        $retval = $stmt->execute($values);
        if (count($this->primaryKey) == 1) {
            $pk = $this->primaryKey[0];
            try {
                $object->$pk = $this->adapter->lastInsertId($this->identifier);
                $this->load($object);
            } catch (PDOException $e) {
                // Means this is not supported by this engine.
            }
        }
        return $retval;
    }

    /**
     * Persist the existing Container $object back to the RDBMS.
     *
     * @param Ornament\Container $object The model to persist.
     * @return boolean True on success, else false.
     */
    public function update(Container $object)
    {
        $sql = "UPDATE %1\$s SET %2\$s WHERE %3\$s";
        $placeholders = [];
        $values = [];
        foreach ($this->fields as $field) {
            $placeholders[$field] = sprintf('%s = ?', $field);
            $values[] = $object->$field;
        }
        $primaries = [];
        foreach ($this->primaryKey as $key) {
            $primaries[] = sprintf('%s = ?', $key);
            $values[] = $object->$key;
        }
        $sql = sprintf(
            $sql,
            $this->identifier,
            implode(', ', $placeholders),
            implode(' AND ', $primaries)
        );
        $stmt = $this->getStatement($sql);
        $retval = $stmt->execute($values);
        $this->load($object);
        return $retval;
    }

    /**
     * Delete the existing Container $object from the RDBMS.
     *
     * @param Ornament\Container $object The model to delete.
     * @return boolean True on success, else false.
     */
    public function delete(Container $object)
    {
        $sql = "DELETE FROM %1\$s WHERE %2\$s";
        $primaries = [];
        foreach ($this->primaryKey as $key) {
            $primaries[] = sprintf('%s = ?', $key);
            $values[] = $object->$key;
        }
        $sql = sprintf(
            $sql,
            $this->identifier,
            implode(' AND ', $primaries)
        );
        $stmt = $this->getStatement($sql);
        $retval = $stmt->execute($values);
        return $retval;
    }
}

