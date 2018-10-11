<?php
include_once 'Reorderer.php';
include_once 'RequestHandler.php';
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RequestHandler::handle($_POST);
}