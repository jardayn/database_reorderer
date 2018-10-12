<?php


class Reorderer
{
    /** @var string */
    private $dbName;
    /** @var PDO */
    private $connection;
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

    private function reorderAllTables(){
        $allTables = $this->getAllDbTables();
        foreach($allTables as $table){
            $this->process($table);
        }
    }

    private function generateAllQueries(){

    }

    private function reorderOneTable($table){
        $description = $this->getDescription($table);
        $this->moveOneColumn();
    }

    private function getDescription($table){
        $query = 'DESCRIBE :tableName';
        $columns = $this->executeQuery($query,['tableName'=>$table]);
        return $columns;
    }

    private function moveOneColumn($table,$prevColumn){

    }


    public function getAllDbTables(){
//        echo 'getin all';
        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema=:dbName;";
        $params = ['dbName'=>$this->dbName];
        return $this->executeQuery($query,$params);
    }

    private function executeQuery($query,array $params = []){
        $stmt = $this->connection->prepare($query);

        foreach($params as $key=>$param){
            $stmt->bindParam(':'.$key,$param);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


}