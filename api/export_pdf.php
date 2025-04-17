<?php
// API для экспорта отчета в PDF
header('Content-Type: application/json; charset=utf-8');

require_once '../config/config.php';
require_once '../includes/functions.php';

// Проверка авторизации
$user = null;
$headers = getallheaders();

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $authHeader);
    $user = validateToken($token);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Неверный токен авторизации']);
        exit;
    }
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Требуется авторизация']);
    exit;
}

// Проверка ролей (доступ только для администратора и аналитика)
if ($user['role'] !== 'administrator' && $user['role'] !== 'analyst') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Доступ запрещен']);
    exit;
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
    exit;
}

// Получение данных из запроса
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['report_id']) && !isset($input['unp'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Необходимо указать report_id или unp']);
    exit;
}

// Получение данных отчета
$reportData = null;

if (isset($input['report_id'])) {
    // Если указан ID отчета, получаем его из БД
    $reportId = (int)$input['report_id'];
    
    if ($user['role'] === 'administrator') {
        $report = getReportById($reportId);
    } else {
        $report = getReportById($reportId, $user['id']);
    }
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Отчет не найден']);
        exit;
    }
    
    $reportData = $report['report_data'];
    $unp = $report['unp'];
} else {
    // Если указан УНП, выполняем новую проверку
    $unp = trim($input['unp']);
    
    // Проверка формата УНП (9 цифр)
    if (!preg_match('/^\d{9}$/', $unp)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Неверный формат УНП']);
        exit;
    }
    
    // Запрос данных из МНС
    $mnsData = getMNSData($unp);
    
    // Запрос данных из JustBel
    $justbelData = getJustBelData($unp);
    
    // Расчет рейтинга надежности
    $reliability = calculateReliabilityRating($mnsData, $justbelData);
    
    // Формирование данных отчета
    $reportData = [
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
    ];
}

// Подключаем библиотеку TCPDF
require_once('../assets/libs/tcpdf/tcpdf.php');

// Создаем новый PDF документ
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Устанавливаем информацию о документе
$pdf->SetCreator('Контрагент.Проверка');
$pdf->SetAuthor('Контрагент.Проверка');
$pdf->SetTitle('Отчет о проверке контрагента ' . $unp);
$pdf->SetSubject('Отчет о проверке контрагента');
$pdf->SetKeywords('контрагент, проверка, отчет, УНП');

// Устанавливаем данные заголовка по умолчанию
$pdf->SetHeaderData('', 0, 'Контрагент.Проверка', 'Отчет о проверке контрагента ' . $unp . ' от ' . date('d.m.Y'));

// Устанавливаем шрифты для заголовка и нижнего колонтитула
$pdf->setHeaderFont(Array('freesans', '', 10));
$pdf->setFooterFont(Array('freesans', '', 8));

// Устанавливаем моноширинный шрифт по умолчанию
$pdf->SetDefaultMonospacedFont('freemono');

// Устанавливаем отступы
$pdf->SetMargins(15, 27, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Устанавливаем автоматические разрывы страниц
$pdf->SetAutoPageBreak(TRUE, 25);

// Устанавливаем шрифт
$pdf->SetFont('freesans', '', 10);

// Добавляем страницу
$pdf->AddPage();

// Формируем содержимое PDF

// Заголовок отчета
$pdf->SetFont('freesans', 'B', 16);
$pdf->Write(0, 'Отчет о проверке контрагента', '', 0, 'C', true, 0, false, false, 0);
$pdf->Ln(10);

// Информация о компании
$pdf->SetFont('freesans', 'B', 14);
$pdf->Write(0, 'Информация о компании', '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(5);

// Таблица с информацией о компании
$pdf->SetFont('freesans', '', 10);

if ($reportData['company_info']) {
    $companyInfo = $reportData['company_info'];
    $html = '
    <table border="1" cellpadding="5">
        <tr>
            <td width="30%"><b>УНП</b></td>
            <td width="70%">' . htmlspecialchars($companyInfo['unp']) . '</td>
        </tr>
        <tr>
            <td><b>Полное наименование</b></td>
            <td>' . htmlspecialchars($companyInfo['name']) . '</td>
        </tr>
        <tr>
            <td><b>Краткое наименование</b></td>
            <td>' . htmlspecialchars($companyInfo['short_name']) . '</td>
        </tr>
        <tr>
            <td><b>Адрес</b></td>
            <td>' . htmlspecialchars($companyInfo['address']) . '</td>
        </tr>
        <tr>
            <td><b>Дата регистрации</b></td>
            <td>' . htmlspecialchars($companyInfo['registration_date']) . '</td>
        </tr>
        <tr>
            <td><b>Налоговая инспекция</b></td>
            <td>' . htmlspecialchars($companyInfo['tax_office']) . '</td>
        </tr>
        <tr>
            <td><b>Статус</b></td>
            <td>' . htmlspecialchars($companyInfo['status']) . '</td>
        </tr>
    </table>';
    $pdf->writeHTML($html, true, false, true, false, '');
} else {
    $pdf->Write(0, 'Информация о компании недоступна', '', 0, 'L', true);
}

$pdf->Ln(10);

// Рейтинг надежности
$pdf->SetFont('freesans', 'B', 14);
$pdf->Write(0, 'Рейтинг надежности', '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(5);

$pdf->SetFont('freesans', '', 10);
if ($reportData['reliability_rating']) {
    $rating = $reportData['reliability_rating'];
    $score = $rating['score'];
    $level = $rating['level'];
    
    // Определение цвета в зависимости от уровня
    $levelText = '';
    $levelColor = '';
    
    if ($level === 'high') {
        $levelText = 'Высокий';
        $levelColor = '0,128,0'; // Зеленый
    } elseif ($level === 'medium') {
        $levelText = 'Средний';
        $levelColor = '255,165,0'; // Оранжевый
    } else {
        $levelText = 'Низкий';
        $levelColor = '255,0,0'; // Красный
    }
    
    $pdf->Write(0, 'Итоговый рейтинг: ' . $score . ' / 100 (' . $levelText . ')', '', 0, 'L', true);
    $pdf->Ln(5);
    
    // Таблица с факторами рейтинга
    if (isset($rating['factors']) && !empty($rating['factors'])) {
        $html = '
        <table border="1" cellpadding="5">
            <tr>
                <th width="60%"><b>Фактор</b></th>
                <th width="20%"><b>Оценка</b></th>
                <th width="20%"><b>Вес</b></th>
            </tr>';
        
        foreach ($rating['factors'] as $factor) {
            $factorName = translateFactorName($factor['name']);
            $factorScore = $factor['score'];
            $factorWeight = $factor['weight'];
            
            // Определение цвета оценки
            $scoreColor = '255,0,0'; // По умолчанию красный
            if ($factorScore >= 80) {
                $scoreColor = '0,128,0'; // Зеленый
            } elseif ($factorScore >= 50) {
                $scoreColor = '255,165,0'; // Оранжевый
            }
            
            $html .= '
            <tr>
                <td>' . htmlspecialchars($factorName) . '</td>
                <td><span color="rgb(' . $scoreColor . ')">' . $factorScore . '</span></td>
                <td>' . $factorWeight . '%</td>
            </tr>';
        }
        
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
    }
} else {
    $pdf->Write(0, 'Рейтинг надежности не может быть рассчитан из-за отсутствия данных.', '', 0, 'L', true);
}

$pdf->Ln(10);

// Информация о ликвидации
$pdf->SetFont('freesans', 'B', 14);
$pdf->Write(0, 'Информация о ликвидации', '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(5);

$pdf->SetFont('freesans', '', 10);
if ($reportData['liquidation_info'] && !empty($reportData['liquidation_info'])) {
    $html = '
    <table border="1" cellpadding="5">
        <tr>
            <th width="25%"><b>Дата решения</b></th>
            <th width="25%"><b>Дата ликвидации</b></th>
            <th width="25%"><b>Статус</b></th>
            <th width="25%"><b>Ликвидатор</b></th>
        </tr>';
    
    foreach ($reportData['liquidation_info'] as $item) {
        $decisionDate = isset($item['liquidationDecisionDate']) ? date('d.m.Y', strtotime($item['liquidationDecisionDate'])) : 'Нет данных';
        $liquidationDate = isset($item['liquidationDate']) ? date('d.m.Y', strtotime($item['liquidationDate'])) : 'Нет данных';
        
        // Получение текстового представления статуса
        $entityState = 'Нет данных';
        if (isset($item['entityState'])) {
            switch ($item['entityState']) {
                case 1:
                    $entityState = 'В процессе ликвидации';
                    break;
                case 2:
                    $entityState = 'Ликвидирован';
                    break;
                default:
                    $entityState = 'Неизвестно (' . $item['entityState'] . ')';
            }
        }
        
        $html .= '
        <tr>
            <td>' . $decisionDate . '</td>
            <td>' . $liquidationDate . '</td>
            <td>' . $entityState . '</td>
            <td>' . (isset($item['liquidatorName']) ? htmlspecialchars($item['liquidatorName']) : 'Нет данных') . '</td>
        </tr>';
    }
    
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
} else {
    $pdf->Write(0, 'Информация о ликвидации отсутствует.', '', 0, 'L', true);
}

$pdf->Ln(10);

// Статус источников данных
$pdf->SetFont('freesans', 'B', 14);
$pdf->Write(0, 'Статус источников данных', '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(5);

$pdf->SetFont('freesans', '', 10);
$html = '
<table border="1" cellpadding="5">
    <tr>
        <th width="50%"><b>Источник</b></th>
        <th width="50%"><b>Статус</b></th>
    </tr>
    <tr>
        <td>Налоговая служба</td>
        <td>' . ($reportData['sources_status']['tax_service']['available'] ? 'Доступен' : 'Недоступен: ' . $reportData['sources_status']['tax_service']['error']) . '</td>
    </tr>
    <tr>
        <td>Сервис ликвидации</td>
        <td>' . ($reportData['sources_status']['liquidation_service']['available'] ? 'Доступен' : 'Недоступен: ' . $reportData['sources_status']['liquidation_service']['error']) . '</td>
    </tr>
</table>';
$pdf->writeHTML($html, true, false, true, false, '');

// Информация о создании отчета
$pdf->Ln(15);
$pdf->SetFont('freesans', 'I', 8);
$pdf->Write(0, 'Отчет сформирован ' . date('d.m.Y H:i:s') . ' пользователем ' . $user['username'], '', 0, 'L', true);

// Сохранение PDF
$pdfFileName = 'report_' . $unp . '_' . date('Ymd_His') . '.pdf';

// Создаем абсолютный путь к директории tmp
$tmpDir = __DIR__ . '/../tmp';

// Создаем директорию tmp, если она не существует
if (!file_exists($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

// Полный путь к файлу PDF
$pdfFilePath = $tmpDir . '/' . $pdfFileName;

// Настраиваем TCPDF, чтобы избежать проблем с open_basedir
// Сохраняем PDF файл
$pdf->Output($pdfFilePath, 'F');

// Возвращаем результат
echo json_encode([
    'status' => 'success',
    'message' => 'PDF успешно сформирован',
    'file' => $pdfFileName,
    'download_url' => 'tmp/index.php?file=' . $pdfFileName
], JSON_UNESCAPED_UNICODE);
?> 