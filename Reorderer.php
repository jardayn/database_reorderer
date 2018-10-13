<?php


class Reorderer
{
    /** @var string */
    private $dbName;
    /** @var PDO */
    private $connection;

    /** @var int  */
    private $columnsChanged = 0;
    public function __construct($host,$user,$password,$dbname,$port)
    {
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
//        We're checking if the table names are correct.
        $allTables = $this->getAllDbTables($dbTables);
        foreach($dbTables as $table){
            $this->reorderOneTable($startColumns,$endColumns,$table);
        }
    }

    private function generateAllQueries($startColumns,$endColumns,$table){
        try{
            $dbColumns = $this->getDescription($table);

            $query = "ALTER TABLE $table ";
//            var_dump($table);
//            var_dump($dbColumns);
            $this->columnsChanged = 0;
            $this->generateColumnOrder($startColumns,$dbColumns,$query);
            $lastColumn = end($dbColumns);
            $this->generateColumnOrder($endColumns,$dbColumns,$query,$lastColumn['Field']);
            var_dump($query);
            if($this->columnsChanged === 0){
                return null;
            } else {
                return $query;
            }
        } catch (Exception $e){
            var_dump($e);
        }
    }


    private function generateColumnOrder($startColumns,$dbColumns,&$query,$previousColumn = null){
        $startColumns = array_intersect($startColumns,array_column($dbColumns,'Field'));
//        var_dump($dbColumns);
        foreach($startColumns as $column){
            $columnData = $this->searchArrayByValue($dbColumns,'Field',$column);
            $columnName = $columnData['Field'];
            if($previousColumn === $columnName){
                continue;
            }
            $nullable = ($columnData['Null'] === 'NO' ? 'NOT NULL' : '');
            $default = ($columnData['Default'] === null ? '' : 'DEFAULT '.$columnData['Default']);
            $extra = $columnData['Extra'];
            $columnName = $columnData['Field'];
//            var_dump($columnData);
//            die();
            $columnType = $columnData['Type'];
            if($previousColumn !== null){
                $order = 'AFTER '.$previousColumn;
            } else {
                $order = 'FIRST';
            }
            $previousColumn = $columnName;

//            to in_array or not to in_array, that is the question
            if(strlen($extra) >0 && strlen($extra) != 14 ){
                die('You have something else in your extra except Auto_increment. Please tell me what.  '.$extra);
            }
            $query .= 'MODIFY COLUMN '.$columnName.' '.$columnType.' '.$nullable.' '.$default.' '.$extra.' '.$order.',';
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
//ALTER TABLE pocomos_invoices MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT AFTER date_modified;
    private function generateColumnScription($columns,$name){
//        $column = $columns[]
//        return 'MODIFY COLUMN '.
    }





    private function reorderOneTable($startColumns,$endColumns,$table){
        $tableReorderingQuery = $this->generateAllQueries($startColumns,$endColumns,$table);
    }

    private function getDescription($table){
        $query = 'DESCRIBE '.$table;
        return $this->executeQuery($query);
    }

    private function moveOneColumn($table,$prevColumn){

    }


    public function getAllDbTables(array $dbTables = []){
//        echo 'getin all';
        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema=:dbName ";
        if(count($dbTables) > 0){
//            This is open for SQL injection. But really, if you have the Acc/pass for the DB, it doesn't matter at this point
            $query .= "AND table_name IN (".implode(',',$dbTables).");";
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