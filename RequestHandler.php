<?php


class RequestHandler
{
    public static function handle(array $post){
//        var_dump(($post));
        try {
            $host = $post['host'];
            $port = $post['port'];
            $username = $post['username'];
            $password = $post['password'];
            $dbname = $post['dbname'];

            $reorderer = new Reorderer($host,$username,$password,$dbname,$port);

            $startColumns = isset($post['start_columns']) ? $post['start_columns'] : null;
            $end_columns = isset($post['end_columns']) ? $post['end_columns'] : null;
            $dbTables = isset($post['tables_to_adjust']) ? $post['tables_to_adjust'] : null;

            if(isset($dbTables)){
               echo json_response(200,200);
            } else {
                echo json_response(['dbTables'=>$reorderer->getAllDbTables()]);
            }
        } catch (Exception $e) {
            echo json_response($e->getMessage(),500);
        }
    }

    public static function issetOrNull($value){
        if(isset($value)){
            return $value;
        }
        return null;
    }

}