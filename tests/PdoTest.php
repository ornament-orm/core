<?php

use Ornament\Adapter;

class MyTableModel
{
    use Ornament\Model;
    use Ornament\Query;

    /** @Private */
    private $pdo;

    public $id;
    public $name;
    public $comment;

    public function __construct()
    {
        $this->pdo = $GLOBALS['pdo'];
        $this->addAdapter(new Adapter\Pdo($this->pdo));
    }
}

class LinkedTableModel
{
    use Ornament\Model;
    use Ornament\Autoload;

    /** @Private */
    private $pdo;

    public $id;
    /**
     * @Model MyTableModel
     * @Constructor [ pdo ]
     */
    public $mytable;
    public $points;

    public function __construct()
    {
        $this->pdo = $GLOBALS['pdo'];
        $this->addAdapter(new Adapter\Pdo($this->pdo));
    }

    public function getPercentage()
    {
        return round(($this->points / 5) * 100);
    }

    public function __index($index)
    {
    }
}

class PdoTest extends PHPUnit_Extensions_Database_TestCase
{
    private static $pdo;
    private $conn;

    /**
     * @covers Ornament\Storage::dirty
     * @covers Ornament\Model::load
     */
    public function testModel()
    {
        $model = new MyTableModel(self::$pdo);
        $model->name = 'Marijn';
        $model->comment = 'Hi Ornament';
        $model->save();
        $stmt = self::$pdo->prepare("SELECT * FROM my_table");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $this->assertEquals(4, count($rows));
        $model->comment = 'Awesome';
        $model->save();
        $stmt->execute();
        $row = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals('Awesome', $row[3]['comment']);
        unset($model);
        $model = new MyTableModel(self::$pdo);
        $model->id = 1;
        $model->load();
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Notice
     */
    public function testVirtuals()
    {
        $model = new MyTableModel(self::$pdo);
        $model->name = 'Marijn';
        $model->comment = 'Hi Ornament';
        $model->save();
        $linked = new LinkedTableModel(self::$pdo);
        $linked->mytable = $model->id;
        $linked->points = 4;
        $linked->save();
        $this->assertEquals(80, $linked->percentage);
        $linked->percentage = 70;
    }

    public function testAutoload()
    {
        $model = new MyTableModel(self::$pdo);
        $model->name = 'Marijn';
        $model->comment = 'Hi Ornament';
        $model->save();
        $linked = new LinkedTableModel(self::$pdo);
        $linked->mytable = $model->id;
        $linked->points = 4;
        $linked->save();
        unset($model, $linked);
        $linked = new LinkedTableModel(self::$pdo);
        $linked->id = 1;
        $linked->load();
        $this->assertEquals('MyTableModel', get_class($linked->mytable));
    }

    public function testQuery()
    {
        $model = new MyTableModel(self::$pdo);
        $list = $model->query([]);
        $this->assertEquals(3, count($list));
        $this->assertEquals('MyTableModel', get_class($list[0]));
    }

    public function getConnection()
    {
        if ($this->conn === null) {
            if (!isset(self::$pdo)) {
                self::$pdo = new PDO('sqlite::memory:');
                self::$pdo->exec(file_get_contents(__DIR__.'/schema.sql'));
                $GLOBALS['pdo'] = self::$pdo;
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, 'test');
        }
        return $this->conn;
    }
    
    public function getDataSet()
    {
        return $this->createXmlDataSet(__DIR__.'/dataset.xml');
    }
}

