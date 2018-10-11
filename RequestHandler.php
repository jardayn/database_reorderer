<?php


class RequestHandler
{
    public static function handle(array $post){
        var_dump(($post));

        $host = $post['host'];
        $port = $post['host'];
        $username = $post['host'];
        $password = $post['host'];
        $dbname = $post['host'];
        try {
            $reorderer = new Reorderer($host,$username,$password,$dbname,$port);
        } catch (Exception $e) {
            return $e->getMessage();
            die($e->getMessage());
        }

        $startColumns = $post['start_column'];
        $end_columns = $post['end_columns'];



        return 'success';
    }

}