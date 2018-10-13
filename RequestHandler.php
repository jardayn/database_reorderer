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
            $endColumns = isset($post['end_columns']) ? $post['end_columns'] : null;
            $dbTables = isset($post['tables_to_adjust']) ? $post['tables_to_adjust'] : null;

            if(count(array_intersect($startColumns,$endColumns)) > 0) {
                throw new Exception('You cannot have the same columns in both Start and the End sequences');
            }


            if(isset($dbTables)){
               $reorderer->reorderTables($startColumns,$endColumns,$dbTables);
            } else {
                echo json_response(['dbTables'=>$reorderer->getAllDbTables()]);
            }

        } catch (Exception $e) {
//            var_dump($e->getMessage());
            echo json_response($e->getMessage(),500);
        }
    }

}