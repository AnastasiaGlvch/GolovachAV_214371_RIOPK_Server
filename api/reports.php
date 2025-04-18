<?php
// API для работы с отчетами
header('Content-Type: application/json; charset=utf-8');

require_once '../config/config.php';
require_once '../includes/functions.php';

// Проверка авторизации
$user = null;
$headers = getallheaders();

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $authHeader);
    
    // Добавляем обработку ошибок и повторные попытки
    $retryCount = 0;
    $maxRetries = 2;
    
    while ($retryCount <= $maxRetries) {
        $user = validateToken($token);
        
        if ($user) {
            break; // Успешная валидация
        }
        
        
        if ($retryCount < $maxRetries) {
            usleep(100000); // 100 миллисекунд
            $retryCount++;
        } else {
           
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Неверный токен авторизации']);
            exit;
        }
    }
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Требуется авторизация']);
    exit;
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Неверный токен авторизации']);
    exit;
}

// Проверка ролей (доступ только для администратора и аналитика)
if ($user['role'] !== 'administrator' && $user['role'] !== 'analyst') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Доступ запрещен']);
    exit;
}

// Обработка запроса в зависимости от метода
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Получение списка отчетов или конкретного отчета
        if (isset($_GET['id'])) {
            // Получение конкретного отчета
            $reportId = (int)$_GET['id'];
            
            // Проверка прав доступа к отчету
            if ($user['role'] === 'administrator') {
                // Администратор имеет доступ ко всем отчетам
                $report = getReportById($reportId);
            } else {
                // Аналитик имеет доступ только к своим отчетам
                $report = getReportById($reportId, $user['id']);
            }
            
            if (!$report) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Отчет не найден']);
                exit;
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => $report
            ], JSON_UNESCAPED_UNICODE);
        } else {
            // Получение списка отчетов
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            
            if ($user['role'] === 'administrator') {
                // Администратор видит все отчеты
                $result = getAllReports($limit, $offset, $search);
            } else {
                // Аналитик видит только свои отчеты
                $result = getUserReports($user['id'], $limit, $offset, $search);
            }
            
            echo json_encode([
                'status' => 'success',
                'data' => $result['reports'],
                'total' => $result['total']
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'DELETE':
        // Удаление отчета (только для администратора)
        if ($user['role'] !== 'administrator') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Доступ запрещен']);
            exit;
        }
        
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID отчета не указан']);
            exit;
        }
        
        $reportId = (int)$_GET['id'];
        
        try {
            $db = connectDB();
            $sql = "DELETE FROM reports WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $reportId]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Отчет не найден']);
                exit;
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Отчет успешно удален'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Ошибка при удалении отчета']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
}
?> 