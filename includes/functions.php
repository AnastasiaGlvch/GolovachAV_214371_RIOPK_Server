<?php
// Файл с общими функциями

/**
 * Подключение к базе данных
 * @return PDO Объект подключения к БД
 */
function connectDB() {
    try {
        $dsn = "mysql:host=" . MAIN_DB_HOST . ";dbname=" . MAIN_DB_NAME . ";charset=utf8";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        return new PDO($dsn, MAIN_DB_USER, MAIN_DB_PASSWORD, $options);
    } catch (PDOException $e) {
        throw new Exception("Ошибка подключения к БД: " . $e->getMessage());
    }
}

/**
 * Проверка JWT токена
 * @param string $token JWT токен
 * @return array|bool Данные пользователя или false в случае ошибки
 */
function validateToken($token) {
    // Статический кеш пользователей внутри процесса
    static $cachedUsers = [];
    static $lastValidationTime = [];
    static $requestCounter = 0;
    
    // Проверяем кеш, чтобы избежать частых запросов к сервису авторизации
    $cacheKey = md5($token);
    
    // Увеличиваем счетчик запросов
    $requestCounter++;
    
    // Если токен был недавно проверен и успешно валидирован, используем кешированные данные
    // для предотвращения перегрузки сервиса авторизации при быстрых последовательных запросах
    if (isset($cachedUsers[$cacheKey])) {
        $currentTime = time();
        $lastChecked = isset($lastValidationTime[$cacheKey]) ? $lastValidationTime[$cacheKey] : 0;
        $timeDiff = $currentTime - $lastChecked;
        
        // Если токен был проверен менее 60 секунд назад или это быстрый последовательный запрос,
        // используем кеш без обращения к сервису авторизации
        if ($timeDiff < 60 || $requestCounter % 5 !== 0) {
            // Проверяем локально срок действия токена
            $tokenParts = explode('.', $token);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
                if (isset($payload['exp']) && $payload['exp'] > time()) {
                    // Токен всё ещё валиден по времени, возвращаем кешированные данные
                    return $cachedUsers[$cacheKey];
                }
            }
        }
    }
    
    // Декодируем токен для локальной проверки
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        return false; // Неверный формат токена
    }
    
    // Декодируем payload
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
    
    // Проверяем срок действия токена
    if (!isset($payload['exp']) || $payload['exp'] <= time()) {
        // Токен просрочен, удаляем из кеша
        if (isset($cachedUsers[$cacheKey])) {
            unset($cachedUsers[$cacheKey]);
            unset($lastValidationTime[$cacheKey]);
        }
        return false;
    }
    
    // Теперь делаем проверку через API, только если:
    // 1. Это первая проверка токена
    // 2. Прошло более 60 секунд с последней проверки
    // 3. Каждый 5-й запрос для периодической валидации
    
    $isFirstCheck = !isset($cachedUsers[$cacheKey]);
    $isTimeToRevalidate = isset($lastValidationTime[$cacheKey]) && (time() - $lastValidationTime[$cacheKey] > 60);
    $isPeriodicCheck = $requestCounter % 5 === 0;
    
    if ($isFirstCheck || $isTimeToRevalidate || $isPeriodicCheck) {
        $url = AUTH_SERVICE_URL . "/api/validate-token.php";
        
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n" .
                            "Authorization: Bearer " . $token . "\r\n",
                'method' => 'GET',
                'timeout' => API_TIMEOUT
            ]
        ];
        
        $context = stream_context_create($options);
        
        // Установим обработку ошибок
        $previousErrorReporting = error_reporting(0);
        $result = @file_get_contents($url, false, $context);
        error_reporting($previousErrorReporting);
        
        // Проверяем наличие ошибок сети
        if ($result === false) {
            $error = error_get_last();
            error_log("Ошибка при проверке токена: " . (isset($error['message']) ? $error['message'] : 'Неизвестная ошибка'));
            
            // Если у нас уже есть кешированные данные, используем их
            if (isset($cachedUsers[$cacheKey])) {
                return $cachedUsers[$cacheKey];
            }
            
            // Иначе пытаемся выполнить локальную валидацию
            if (isset($payload['user'])) {
                // Кешируем результат на короткое время
                $cachedUsers[$cacheKey] = $payload['user'];
                $lastValidationTime[$cacheKey] = time();
                return $payload['user'];
            }
            
            return false;
        }
        
        $data = json_decode($result, true);
        
        if (isset($data['status']) && $data['status'] === 'success') {
            // Кешируем результат
            $cachedUsers[$cacheKey] = $data['data']['user'];
            $lastValidationTime[$cacheKey] = time();
            return $data['data']['user'];
        }
        
        // API вернул ошибку, удаляем из кеша
        if (isset($cachedUsers[$cacheKey])) {
            unset($cachedUsers[$cacheKey]);
            unset($lastValidationTime[$cacheKey]);
        }
        
        return false;
    }
    
    // Для остальных случаев используем кешированные данные
    if (isset($cachedUsers[$cacheKey])) {
        return $cachedUsers[$cacheKey];
    }
    
    // Если нет кешированных данных, но токен кажется валидным по структуре и времени
    if (isset($payload['user'])) {
        // Кешируем результат
        $cachedUsers[$cacheKey] = $payload['user'];
        $lastValidationTime[$cacheKey] = time();
        return $payload['user'];
    }
    
    return false;
}

/**
 * Получение данных о компании из API МНС
 * @param string $unp УНП компании
 * @return array Данные о компании
 */
function getMNSData($unp) {
    $url = MNS_API_URL . "?unp=" . urlencode($unp) . "&charset=UTF-8&type=json";
    
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => API_TIMEOUT,
            'header' => "Content-Type: application/json\r\n"
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        return [
            'available' => false,
            'error' => 'Сервис МНС недоступен'
        ];
    }
    
    $data = json_decode($result, true);
    
    if (!isset($data['row'])) {
        return [
            'available' => true,
            'data' => null,
            'message' => 'Компания не найдена'
        ];
    }
    
    return [
        'available' => true,
        'data' => $data['row']
    ];
}

/**
 * Получение данных о ликвидации из API JustBel
 * @param string $unp УНП компании
 * @return array Данные о ликвидации
 */
function getJustBelData($unp) {
    $url = JUSTBEL_API_URL . "?registrationNumber=" . urlencode($unp) . "&entityState=all&page=1&court=false";
    
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => API_TIMEOUT
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        return [
            'available' => false,
            'error' => 'Сервис JustBel недоступен'
        ];
    }
    
    $data = json_decode($result, true);
    
    if (!isset($data['items']) || empty($data['items'])) {
        return [
            'available' => true,
            'data' => null,
            'message' => 'Информация о ликвидации не найдена'
        ];
    }
    
    return [
        'available' => true,
        'data' => $data['items']
    ];
}

/**
 * Расчет рейтинга надежности
 * @param array $mnsData Данные из МНС
 * @param array $justbelData Данные из JustBel
 * @return array Рейтинг надежности и факторы
 */
function calculateReliabilityRating($mnsData, $justbelData) {
    global $reliability_factors;
    
    // Инициализация оценок факторов
    $factorScores = [
        'company_status' => 0,
        'company_age' => 0,
        'liquidation' => 0,
        'tax_registration' => 0
    ];
    
    // 1. Оценка фактора "Регистрация в налоговой"
    if ($mnsData['available'] && $mnsData['data']) {
        $factorScores['tax_registration'] = 100; // Есть в реестре МНС
    } else {
        $factorScores['tax_registration'] = 0; // Отсутствует в реестре
    }
    
    // 2. Оценка фактора "Статус юридического лица"
    if ($mnsData['available'] && $mnsData['data']) {
        $status = isset($mnsData['data']['vkods']) ? $mnsData['data']['vkods'] : '';
        
        if ($status === 'Действующий') {
            $factorScores['company_status'] = 100; // Активное
        } elseif (strpos($status, 'ликвидации') !== false) {
            $factorScores['company_status'] = 20; // В процессе ликвидации
        } else {
            $factorScores['company_status'] = 0; // Ликвидировано или неизвестно
        }
    }
    
    // 3. Оценка фактора "Время существования компании"
    if ($mnsData['available'] && $mnsData['data'] && isset($mnsData['data']['dreg'])) {
        $regDate = strtotime($mnsData['data']['dreg']);
        $years = floor((time() - $regDate) / (365 * 24 * 60 * 60));
        
        if ($years >= 10) {
            $factorScores['company_age'] = 100; // Более 10 лет
        } elseif ($years >= 5) {
            $factorScores['company_age'] = 80; // 5-10 лет
        } elseif ($years >= 3) {
            $factorScores['company_age'] = 60; // 3-5 лет
        } elseif ($years >= 1) {
            $factorScores['company_age'] = 40; // 1-3 года
        } else {
            $factorScores['company_age'] = 20; // Менее 1 года
        }
    }
    
    // 4. Оценка фактора "Наличие процессов ликвидации"
    if ($justbelData['available']) {
        if (!$justbelData['data']) {
            $factorScores['liquidation'] = 100; // Отсутствуют
        } else {
            // Проверяем статус процесса ликвидации
            $inProcess = false;
            foreach ($justbelData['data'] as $item) {
                if (isset($item['entityState']) && $item['entityState'] == 1) {
                    $inProcess = true;
                    break;
                }
            }
            
            if ($inProcess) {
                $factorScores['liquidation'] = 0; // В процессе ликвидации
            } else {
                $factorScores['liquidation'] = 30; // Есть информация о возможной ликвидации
            }
        }
    }
    
    // Расчет итогового рейтинга
    $totalScore = 0;
    $factors = [];
    
    foreach ($factorScores as $factor => $score) {
        $weightedScore = $score * $reliability_factors[$factor];
        $totalScore += $weightedScore;
        
        $factors[] = [
            'name' => $factor,
            'score' => $score,
            'weight' => $reliability_factors[$factor] * 100, // в процентах
            'weighted_score' => $weightedScore
        ];
    }
    
    // Определение уровня надежности
    $reliabilityLevel = '';
    if ($totalScore >= 80) {
        $reliabilityLevel = 'high'; // Высокая надежность
    } elseif ($totalScore >= 50) {
        $reliabilityLevel = 'medium'; // Средняя надежность
    } else {
        $reliabilityLevel = 'low'; // Низкая надежность
    }
    
    return [
        'score' => round($totalScore),
        'level' => $reliabilityLevel,
        'factors' => $factors
    ];
}

/**
 * Сохранение отчета в базу данных
 * @param int $userId ID пользователя
 * @param string $unp УНП компании
 * @param array $data Данные отчета
 * @return int|bool ID созданного отчета или false в случае ошибки
 */
function saveReport($userId, $unp, $data) {
    try {
        $db = connectDB();
        
        $sql = "INSERT INTO reports (user_id, unp, report_data, created_at) VALUES (:user_id, :unp, :report_data, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':unp' => $unp,
            ':report_data' => json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);
        
        return $db->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Получение отчетов пользователя
 * @param int $userId ID пользователя
 * @param int $limit Лимит записей
 * @param int $offset Смещение
 * @param string $search Строка поиска по УНП
 * @return array Массив отчетов и общее количество
 */
function getUserReports($userId, $limit = 10, $offset = 0, $search = '') {
    try {
        $db = connectDB();
        
        // Проверяем существование пользователя в системе авторизации
        $user = getUserFromAuthService($userId);
        if (!$user) {
            return ['reports' => [], 'total' => 0];
        }
        
        // Базовое условие для запросов
        $whereClause = "user_id = :user_id";
        $params = [':user_id' => $userId];
        
        // Добавляем условие поиска, если указан параметр search
        if (!empty($search)) {
            $whereClause .= " AND unp LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }
        
        // Сначала получаем общее количество записей
        $countSql = "SELECT COUNT(*) FROM reports WHERE $whereClause";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetchColumn();
        
        // Затем получаем данные с учетом limit/offset
        $sql = "SELECT id, unp, created_at FROM reports WHERE $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $reports = $stmt->fetchAll();
        
        return ['reports' => $reports, 'total' => $total];
    } catch (Exception $e) {
        error_log("Ошибка при получении отчетов пользователя: " . $e->getMessage());
        return ['reports' => [], 'total' => 0];
    }
}

/**
 * Получение информации о пользователе из сервиса авторизации
 * @param int $userId ID пользователя
 * @return array|bool Данные пользователя или false в случае ошибки
 */
function getUserFromAuthService($userId) {
    static $cachedUserData = [];
    
    // Проверка кеша в рамках одного запроса
    if (isset($cachedUserData[$userId])) {
        return $cachedUserData[$userId];
    }
    
    // Получаем текущий токен из заголовков запроса
    $headers = getallheaders();
    $token = '';
    
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } else {
        error_log("Ошибка: Отсутствует токен авторизации при запросе пользователя ID: " . $userId);
        return false;
    }
    
    $url = AUTH_SERVICE_URL . "/api/users/user.php?id=" . $userId;
    
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => API_TIMEOUT,
            'header' => "Content-Type: application/json\r\n" .
                       "Authorization: Bearer " . $token . "\r\n"
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Отключаем вывод ошибок в поток вывода
    $previousErrorReporting = error_reporting(0);
    $result = @file_get_contents($url, false, $context);
    error_reporting($previousErrorReporting);
    
    if ($result === false) {
        $error = error_get_last();
        error_log("Ошибка при получении данных пользователя ID " . $userId . ": " . 
                 (isset($error['message']) ? $error['message'] : 'Неизвестная ошибка'));
        
        // Попробуем использовать резервный подход - получаем данные из локальной БД
        try {
            $db = connectDB();
            $sql = "SELECT user_id FROM reports WHERE user_id = :user_id LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Пользователь существует в нашей БД отчетов, создаем минимальную запись
                $basicUserData = [
                    'id' => $userId,
                    'username' => 'User_' . $userId,
                    'role' => 'analyst' // Предполагаем базовую роль
                ];
                
                // Сохраняем в кеш
                $cachedUserData[$userId] = $basicUserData;
                return $basicUserData;
            }
        } catch (Exception $e) {
            error_log("Ошибка при резервном получении пользователя: " . $e->getMessage());
        }
        
        return false;
    }
    
    $data = json_decode($result, true);
    
    if (isset($data['status']) && $data['status'] === 'success' && isset($data['data'])) {
        // Сохраняем в кеш
        $cachedUserData[$userId] = $data['data'];
        return $data['data'];
    }
    
    error_log("Ошибка при разборе данных пользователя ID " . $userId . ": " . 
             (isset($data['message']) ? $data['message'] : 'Неизвестная ошибка'));
    return false;
}

/**
 * Получение отчета по ID
 * @param int $reportId ID отчета
 * @param int|null $userId ID пользователя (для проверки доступа)
 * @return array|bool Данные отчета или false в случае ошибки
 */
function getReportById($reportId, $userId = null) {
    try {
        $db = connectDB();
        
        $sql = "SELECT * FROM reports WHERE id = :id";
        if ($userId !== null) {
            $sql .= " AND user_id = :user_id";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $reportId, PDO::PARAM_INT);
        
        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $report = $stmt->fetch();
        
        if ($report) {
            $report['report_data'] = json_decode($report['report_data'], true);
        }
        
        return $report;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Получение всех отчетов (для администратора)
 * @param int $limit Лимит записей
 * @param int $offset Смещение
 * @param string $search Строка поиска по УНП
 * @return array Массив отчетов и общее количество
 */
function getAllReports($limit = 10, $offset = 0, $search = '') {
    try {
        $db = connectDB();
        
        // Проверяем подключение к БД
        if (!$db) {
            error_log("Ошибка: Не удалось подключиться к базе данных в функции getAllReports");
            return ['reports' => [], 'total' => 0];
        }
        
        // Базовое условие для запросов
        $whereClause = "1=1";
        $params = [];
        
        // Добавляем условие поиска, если указан параметр search
        if (!empty($search)) {
            $whereClause .= " AND r.unp LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }
        
        // Сначала получаем общее количество записей
        $countSql = "SELECT COUNT(*) FROM reports r WHERE $whereClause";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetchColumn();
        
        // Затем получаем данные с учетом limit/offset
        $sql = "SELECT r.id, r.unp, r.created_at, r.user_id, r.report_data 
                FROM reports r 
                WHERE $whereClause
                ORDER BY r.created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $reports = $stmt->fetchAll();
        
        // Если отчеты найдены, дополним информацией о пользователях
        if (!empty($reports)) {
            // Получаем уникальные ID пользователей
            $userIds = array_unique(array_column($reports, 'user_id'));
            
            // Получаем информацию о каждом пользователе
            $users = [];
            foreach ($userIds as $userId) {
                $user = getUserFromAuthService($userId);
                if ($user) {
                    $users[$userId] = $user;
                }
            }
            
            // Добавляем информацию о пользователях к отчетам
            foreach ($reports as &$report) {
                $userId = $report['user_id'];
                $report['username'] = isset($users[$userId]) ? $users[$userId]['username'] : 'Unknown';
                
                // Декодируем JSON данные отчета
                if (isset($report['report_data'])) {
                    $report['report_data'] = json_decode($report['report_data'], true);
                }
            }
        }
        
        return ['reports' => $reports, 'total' => $total];
    } catch (Exception $e) {
        error_log("Ошибка при получении всех отчетов: " . $e->getMessage());
        return ['reports' => [], 'total' => 0];
    }
}

/**
 * Перевод названий факторов на русский язык
 * @param string $factor Название фактора
 * @return string Русское название фактора
 */
function translateFactorName($factor) {
    $translations = [
        'company_status' => 'Статус юридического лица',
        'company_age' => 'Время существования компании',
        'liquidation' => 'Наличие процессов ликвидации',
        'tax_registration' => 'Регистрация в налоговой'
    ];
    
    return isset($translations[$factor]) ? $translations[$factor] : $factor;
}
?> 