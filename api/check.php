<?php
// API для проверки контрагента
header('Content-Type: application/json; charset=utf-8');

require_once '../config/config.php';
require_once '../includes/functions.php';

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
    exit;
}

// Получение данных из запроса
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['unp']) || empty($input['unp'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'УНП обязателен']);
    exit;
}

$unp = trim($input['unp']);

// Проверка формата УНП (9 цифр)
if (!preg_match('/^\d{9}$/', $unp)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Неверный формат УНП']);
    exit;
}

// Получаем данные о пользователе из токена
$user = null;
$headers = getallheaders();

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $authHeader);
    $user = validateToken($token);
}

// Запрос данных из МНС
$mnsData = getMNSData($unp);

// Запрос данных из JustBel
$justbelData = getJustBelData($unp);

// Расчет рейтинга надежности
$reliability = calculateReliabilityRating($mnsData, $justbelData);

// Формирование результата
$result = [
    'status' => 'success',
    'data' => [
        'company_info' => $mnsData['available'] && $mnsData['data'] ? [
            'unp' => $mnsData['data']['vunp'] ?? $unp,
            'name' => $mnsData['data']['vnaimp'] ?? '',
            'short_name' => $mnsData['data']['vnaimk'] ?? '',
            'address' => $mnsData['data']['vpadres'] ?? '',
            'registration_date' => $mnsData['data']['dreg'] ?? '',
            'tax_office' => $mnsData['data']['vmns'] ?? '',
            'status' => $mnsData['data']['vkods'] ?? ''
        ] : null,
        'sources_status' => [
            'tax_service' => [
                'available' => $mnsData['available'],
                'error' => $mnsData['available'] ? null : ($mnsData['error'] ?? 'Неизвестная ошибка')
            ],
            'liquidation_service' => [
                'available' => $justbelData['available'],
                'error' => $justbelData['available'] ? null : ($justbelData['error'] ?? 'Неизвестная ошибка')
            ]
        ],
        'liquidation_info' => $justbelData['available'] && $justbelData['data'] ? $justbelData['data'] : null,
        'reliability_rating' => $reliability
    ]
];

// Если хотя бы один из сервисов недоступен, меняем статус на partial
if (!$mnsData['available'] || !$justbelData['available']) {
    $result['status'] = 'partial';
}

// Если оба сервиса недоступны, меняем статус на error
if (!$mnsData['available'] && !$justbelData['available']) {
    $result['status'] = 'error';
}

// Сохранение отчета в БД, если пользователь авторизован
if ($user && isset($user['id']) && ($user['role'] === 'administrator' || $user['role'] === 'analyst')) {
    $reportId = saveReport($user['id'], $unp, $result['data']);
    if ($reportId) {
        $result['data']['report_id'] = $reportId;
    }
}

// Возвращаем результат
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?> 