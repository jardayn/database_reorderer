<?php


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
//          Gotta get rid of that pesky comma.
//          At this point, I don't even know which approach is crappier. This or checking for lastKey in the foreach
            $length = ','.PHP_EOL;
            return substr($query,0,(strlen($query)-strlen($length))).';';

        } catch (Exception $e){
            var_dump($e);
        }
    }


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
//            @TODO Implement support for stuff aside auto_increment
            if(strlen($extra) >0 && strlen($extra) != 14 ){
                die('You have something else in your extra except Auto_increment. Dont wanna break anything in yo app, will fix this later.  '.$extra);
            }
            $query .= 'MODIFY COLUMN '.$columnName.' '.$columnType.' '.$nullable.' '.$default.' '.$extra.' '.$order.','.PHP_EOL;
            $this->columnsChanged++;
        }
    }

    private function searchArrayByValue($array,$key,$value){
        foreach($array as $elem){
            if($elem[$key] === $value){
//                var_dump($elem);
                return $elem;
            }
        }
        return null;
    }

    private function reorderOneTable($table){
        $tableReorderingQuery = $this->generateReorderQuery($table);
        if($tableReorderingQuery && $this->executeQueries){
            $this->executeQuery($tableReorderingQuery);
        }
        return $tableReorderingQuery;

    }

    private function getDescription($table){
        $query = 'DESCRIBE '.$table;
        return $this->executeQuery($query);
    }

    public function getAllDbTables(array $dbTables = []){
        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema=:dbName ";
        if(count($dbTables) > 0){
//            This is open for SQL injection. But really, if you have the Acc/pass for the DB, it doesn't matter at this point
            $query .= "AND table_name IN ('".implode("','",$dbTables)."');";
        }
        $params = ['dbName'=>$this->dbName];
        return $this->executeQuery($query,$params,PDO::FETCH_COLUMN);
    }


    private function executeQuery($query,array $params = [],$fetchMode = PDO::FETCH_ASSOC){
        $stmt = $this->connection->prepare($query);

        foreach($params as $key=>$param){
            $stmt->bindParam(':'.$key,$param);
        }

        $stmt->execute();
        return $stmt->fetchAll($fetchMode);
    }


}