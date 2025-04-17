<?php

require_once __DIR__ . '/../../src/controllers/UserController.php';


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

$id = intval($_GET['id']);
$userController = new UserController();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      
        $userController->getById($id);
        break;
    
    case 'PUT':
      
        $userController->update($id);
        break;
    
    case 'DELETE':
      
        $userController->delete($id);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
} 