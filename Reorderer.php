<?php

/**
 * Class Reorderer
 */
class Reorderer
{
    /** @var string */
    private $dbName;
    /** @var PDO */
    private $connection;

    /** @var int  */
    private $columnsChanged = 0;

    /** @var bool  */
    private $executeQueries;

    /** @var array */
    private $startColumns;
    /** @var array */
    private $endColumns;

    /**
     * Reorderer constructor.
     *
     * @param $host
     * @param $user
     * @param $password
     * @param $dbname
     * @param $port
     * @param bool $executeQueries
     * @throws Exception
     */
    public function __construct($host,$user,$password,$dbname,$port,$executeQueries = false)
    {
        $this->executeQueries = $executeQueries;
        $this->dbName = $dbname;
        $dsn = "mysql:dbname=$dbname;host=$host;port=$port";
        try {
            $this->connection = new PDO($dsn,$user,$password);
        }
        catch (Exception $e){
            throw $e;
        }
    }

    /**
     * @param array $startColumns
     * @param array $endColumns
     * @param array $dbTables
     * @return array
     */
    public function reorderTables(array $startColumns,array $endColumns,array $dbTables){
        $this->startColumns = $startColumns;
        $this->endColumns = $endColumns;
//        We're checking if the table names are correct.
        $allTables = $this->getAllDbTables($dbTables);
        $queries = [];
        foreach($allTables as $table){
            $query = $this->reorderOneTable($table);
            if($query !== null){
                $queries[] = $query;
            }

        }
        return $queries;
    }

    /**
     * @param $tableColumns
     * @param $proposedOrder
     * @param bool $orderFromTheStart
     * @return array
     */
    private function verifyOrder($tableColumns,$proposedOrder,$orderFromTheStart = true){
        $tableColumnNames = array_column($tableColumns,'Field');
        $proposedOrder = array_intersect($proposedOrder,$tableColumnNames);
//        Array Intersect preserves the keys. So we need to reset them to have the same keys as the Table Columns.
        $proposedOrder = array_values($proposedOrder);
        $lengthToCheck = ($a = count($tableColumnNames) < $b = count($proposedOrder) ) ? $a : $b;
        $previousColumn = null;


        for($i = 0; $i < $lengthToCheck; $i++){
            if($tableColumnNames[$i] === $proposedOrder[$i]){
                $previousColumn = $proposedOrder[$i];
                unset($proposedOrder[$i]);
            } else {
                break;
            }
        }


        return ['previousColumn'=>$previousColumn,'updatedOrder'=>$proposedOrder];
    }

    /**
     * @param $table
     * @return null|string
     */
    private function generateReorderQuery($table){
        try{
            $dbColumns = $this->getDescription($table);

            $query = "ALTER TABLE $table ";
            $this->columnsChanged = 0;
            $updatedOrder = $this->verifyOrder($dbColumns,$this->startColumns);
            $this->generateColumnOrder($updatedOrder['updatedOrder'],$dbColumns,$query,$updatedOrder['previousColumn']);
            $lastColumn = end($dbColumns);
            $this->generateColumnOrder($this->endColumns,$dbColumns,$query,$lastColumn['Field']);

            if($this->columnsChanged === 0){
                return null;
            }
//            Specifying how to run these DDL operations.
            $query.= 'ALGORITHM=INPLACE, LOCK=NONE;';
            return $query;

        } catch (Exception $e){
            var_dump($e);
        }
    }

    /**
     * @param $columnOrder
     * @param $dbColumns
     * @param $query
     * @param null $previousColumn
     */
    private function generateColumnOrder($columnOrder,$dbColumns,&$query,$previousColumn = null){

        $columnOrder = array_intersect($columnOrder,array_column($dbColumns,'Field'));
//        var_dump($dbColumns);
        foreach($columnOrder as $column){
            $columnData = $this->searchArrayByValue($dbColumns,'Field',$column);
            $columnName = $columnData['Field'];
            if($previousColumn === $columnName){
                continue;
            }
            $nullable = ($columnData['Null'] === 'NO' ? 'NOT NULL' : '');
            $default = ($columnData['Default'] === null ? '' : 'DEFAULT '.$columnData['Default']);
            $extra = $columnData['Extra'];
            $columnName = $columnData['Field'];
            $order = ($previousColumn !== null ? 'AFTER '.$previousColumn : 'FIRST');

            $columnType = $columnData['Type'];

            $previousColumn = $columnName;
//            to in_array or not to in_array, that is the question
//            @TODO Implement support for stuff aside from auto_increment
            $extraLength = strlen($extra);
            if($extraLength >0 && $extraLength != 14 ){
                die('You have something else in your extra except Auto_increment. Dont wanna break anything in yo app, will fix this later.  '.$extra);
            }
            $query .= 'MODIFY COLUMN '.$columnName.' '.$columnType.' '.$nullable.' '.$default.' '.$extra.' '.$order.','.PHP_EOL;
            $this->columnsChanged++;
        }
    }

    /**
     * @param $array
     * @param $key
     * @param $value
     * @return null
     */
    private function searchArrayByValue($array,$key,$value){
        foreach($array as $elem){
            if($elem[$key] === $value){
//                var_dump($elem);
                return $elem;
            }
        }
        return null;
    }

    /**
     * @param $table
     * @return null|string
     */
    private function reorderOneTable($table){
        $tableReorderingQuery = $this->generateReorderQuery($table);
        if($tableReorderingQuery && $this->executeQueries){
//            TODO: Implement this stuff
//            $this->executeQuery($tableReorderingQuery);
        }
        return $tableReorderingQuery;

    }

    /**
     * @param $table
     * @return array
     */
    private function getDescription($table){
        $query = 'DESCRIBE '.$table;
        return $this->executeQuery($query);
    }

    /**
     * @param array $dbTables
     * @return array
     */
    public function getAllDbTables(array $dbTables = []){
        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema=:dbName ";
        if(count($dbTables) > 0){
//            This is open for SQL injection. But really, if you have the Acc/pass for the DB, it doesn't matter at this point
            $query .= "AND table_name IN ('".implode("','",$dbTables)."');";
        }
        $params = ['dbName'=>$this->dbName];
        return $this->executeQuery($query,$params,PDO::FETCH_COLUMN);
    }

    /**
     * @param $query
     * @param array $params
     * @param int $fetchMode
     * @return array
     */
    private function executeQuery($query,array $params = [],$fetchMode = PDO::FETCH_ASSOC){
        $stmt = $this->connection->prepare($query);

        foreach($params as $key=>$param){
            $stmt->bindParam(':'.$key,$param);
        }

        $stmt->execute();
        return $stmt->fetchAll($fetchMode);
    }


}