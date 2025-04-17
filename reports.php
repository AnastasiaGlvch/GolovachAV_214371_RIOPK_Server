<?php
// Страница истории отчетов
session_start();

// Подключение необходимых файлов
require_once 'config/config.php';
require_once 'includes/functions.php';

// Рендеринг страницы
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">История проверок</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Главная</a></li>
                <li class="breadcrumb-item active" aria-current="page">История проверок</li>
            </ol>
        </nav>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="alert alert-info rounded-3 shadow-sm auth-required d-none">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
                    <p class="mb-0">Для просмотра истории проверок необходимо войти в систему с правами администратора или аналитика.</p>
                </div>
            </div>
            
            <div class="reports-container auth-only d-none">
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-primary text-opacity-75"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="search-input" placeholder="Поиск по УНП...">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="reports-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>УНП</th>
                                <th>Дата проверки</th>
                                <th class="administrator-only d-none">Пользователь</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Данные будут загружены через AJAX -->
                        </tbody>
                    </table>
                </div>
                
                <nav aria-label="Навигация по страницам" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        <!-- Страницы будут добавлены динамически -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для просмотра отчета -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Отчет о проверке контрагента</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="report-content">
                    <!-- Содержимое отчета будет загружено через AJAX -->
                </div>
                <!-- Индикатор загрузки -->
                <div id="loading" class="text-center d-none py-5">
                    <div class="spinner-grow text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p class="mt-3 text-muted">Подождите, идет формирование отчета...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-success rounded-pill" id="export-pdf-modal-button">
                    <i class="fas fa-file-pdf me-2"></i> Экспорт в PDF
                </button>
                <!-- Кнопка экспорта в Excel отключена из-за проблем с функциональностью -->
                <!-- <button type="button" class="btn btn-primary rounded-pill" id="export-excel-modal-button">
                    <i class="fas fa-file-excel me-2"></i> Экспорт в Excel
                </button> -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Проверка авторизации
    const user = getCurrentUser();
    
    // Переменная для хранения ID текущего отчета
    let currentReportId = null;
    
    if (!user || (user.role !== 'administrator' && user.role !== 'analyst')) {
        // Пользователь не авторизован или не имеет необходимых прав
        $('.auth-required').removeClass('d-none');
    } else {
        // Пользователь авторизован и имеет необходимые права
        $('.reports-container').removeClass('d-none');
        
        // Если пользователь администратор, показываем колонку с именем пользователя
        if (user.role === 'administrator') {
            $('.administrator-only').removeClass('d-none');
        }
        
        // Загружаем отчеты
        loadReports(1);
        
        // Обработчик поиска
        $('#search-input').on('keyup', function() {
            const searchValue = $(this).val().trim();
            if (searchValue.length >= 3 || searchValue.length === 0) {
                loadReports(1);
            }
        });
    }
    
    // Функция для загрузки отчетов
    function loadReports(page, limit = 10) {
        const token = getToken();
        const searchValue = $('#search-input').val().trim();
        
        if (!token) {
            $('#reports-table tbody').html('<tr><td colspan="5" class="text-center text-danger">Ошибка авторизации. Пожалуйста, войдите в систему.</td></tr>');
            return;
        }
        
        // Очищаем таблицу
        $('#reports-table tbody').empty();
        
        // Показываем индикатор загрузки
        $('#reports-table tbody').html('<tr><td colspan="5" class="text-center">Загрузка данных...</td></tr>');
        
        // Вычисляем смещение
        const offset = (page - 1) * limit;
        
        // Формируем URL с учетом поиска
        let url = 'api/reports.php?limit=' + limit + '&offset=' + offset;
        if (searchValue) {
            url += '&search=' + encodeURIComponent(searchValue);
        }
        
        // Настройки для повторных попыток
        let retryCount = 0;
        const maxRetries = 3;
        const retryDelay = 500; // ms
        
        // Функция выполнения запроса с поддержкой повторных попыток
        function executeRequest() {
            $.ajax({
                url: url,
                type: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token
                },
                timeout: 15000, // 15 секунд таймаут
                success: function(response) {
                    if (response.status === 'success') {
                        const reports = response.data;
                        const totalRecords = response.total || 0;
                        
                        // Очищаем таблицу
                        $('#reports-table tbody').empty();
                        
                        if (reports.length === 0) {
                            $('#reports-table tbody').html('<tr><td colspan="5" class="text-center">Нет данных</td></tr>');
                            return;
                        }
                        
                        // Добавляем данные в таблицу
                        reports.forEach(function(report) {
                            let row = '<tr>';
                            row += '<td>' + report.id + '</td>';
                            row += '<td>' + report.unp + '</td>';
                            row += '<td>' + formatDate(report.created_at) + '</td>';
                            
                            // Если пользователь администратор, добавляем колонку с именем пользователя
                            if (user.role === 'administrator') {
                                row += '<td>' + (report.username || 'Неизвестно') + '</td>';
                            }
                            
                            row += '<td>';
                            row += '<button class="btn btn-sm btn-outline-primary rounded-pill view-report-button me-1" data-id="' + report.id + '">';
                            row += '<i class="fas fa-eye me-1"></i> Просмотр';
                            row += '</button>';
                            
                            // Если пользователь администратор, добавляем кнопку удаления
                            if (user.role === 'administrator') {
                                row += ' <button class="btn btn-sm btn-outline-danger rounded-pill delete-report-button" data-id="' + report.id + '">';
                                row += '<i class="fas fa-trash me-1"></i> Удалить';
                                row += '</button>';
                            }
                            
                            row += '</td>';
                            row += '</tr>';
                            
                            $('#reports-table tbody').append(row);
                        });
                        
                        // Настраиваем пагинацию на основе общего количества записей
                        const totalPages = Math.max(1, Math.ceil(totalRecords / limit));
                        
                        $('#pagination').empty();
                        
                        // Добавляем кнопку "Предыдущая"
                        $('#pagination').append(`
                            <li class="page-item ${page === 1 ? 'disabled' : ''}">
                                <a class="page-link rounded-pill mx-1 shadow-sm" href="#" data-page="${page - 1}">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        `);
                        
                        // Добавляем номера страниц
                        // При большом количестве страниц, показывать ограниченное число
                        const maxVisiblePages = 5;
                        let startPage = Math.max(1, page - Math.floor(maxVisiblePages / 2));
                        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                        
                        // Корректировка, если не хватает страниц в конце
                        if (endPage - startPage + 1 < maxVisiblePages) {
                            startPage = Math.max(1, endPage - maxVisiblePages + 1);
                        }
                        
                        if (startPage > 1) {
                            $('#pagination').append(`
                                <li class="page-item">
                                    <a class="page-link rounded-pill mx-1 shadow-sm" href="#" data-page="1">1</a>
                                </li>
                            `);
                            if (startPage > 2) {
                                $('#pagination').append(`
                                    <li class="page-item disabled">
                                        <a class="page-link rounded-pill mx-1 shadow-sm" href="#">...</a>
                                    </li>
                                `);
                            }
                        }
                        
                        for (let i = startPage; i <= endPage; i++) {
                            $('#pagination').append(`
                                <li class="page-item ${i === page ? 'active' : ''}">
                                    <a class="page-link rounded-pill mx-1 shadow-sm" href="#" data-page="${i}">${i}</a>
                                </li>
                            `);
                        }
                        
                        if (endPage < totalPages) {
                            if (endPage < totalPages - 1) {
                                $('#pagination').append(`
                                    <li class="page-item disabled">
                                        <a class="page-link rounded-pill mx-1 shadow-sm" href="#">...</a>
                                    </li>
                                `);
                            }
                            $('#pagination').append(`
                                <li class="page-item">
                                    <a class="page-link rounded-pill mx-1 shadow-sm" href="#" data-page="${totalPages}">${totalPages}</a>
                                </li>
                            `);
                        }
                        
                        // Добавляем кнопку "Следующая"
                        $('#pagination').append(`
                            <li class="page-item ${page >= totalPages ? 'disabled' : ''}">
                                <a class="page-link rounded-pill mx-1 shadow-sm" href="#" data-page="${page + 1}">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        `);
                        
                        // Обработчик для пагинации
                        $('.page-link').on('click', function(e) {
                            e.preventDefault();
                            const pageNum = $(this).data('page');
                            loadReports(pageNum);
                        });
                        
                        // Обработчик для просмотра отчета
                        $('.view-report-button').on('click', function() {
                            const reportId = $(this).data('id');
                            viewReport(reportId);
                        });
                        
                        // Обработчик для удаления отчета
                        $('.delete-report-button').on('click', function() {
                            const reportId = $(this).data('id');
                            deleteReport(reportId);
                        });
                    } else {
                        // Статус успешен, но в ответе ошибка
                        if (retryCount < maxRetries) {
                            retryCount++;
                            setTimeout(executeRequest, retryDelay * retryCount);
                        } else {
                            $('#reports-table tbody').html(`<tr><td colspan="5" class="text-center text-danger">
                                Ошибка при загрузке данных: ${response.message || 'Неизвестная ошибка'}
                                <button class="btn btn-sm btn-outline-primary ms-2 retry-button">Повторить</button>
                            </td></tr>`);
                            
                            // Обработчик для кнопки повторной попытки
                            $('.retry-button').on('click', function() {
                                retryCount = 0;
                                loadReports(page, limit);
                            });
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", status, error);
                    
                    // Проверяем тип ошибки
                    if (status === 'timeout') {
                        // Ошибка таймаута
                        if (retryCount < maxRetries) {
                            retryCount++;
                            setTimeout(executeRequest, retryDelay * retryCount);
                        } else {
                            $('#reports-table tbody').html(`<tr><td colspan="5" class="text-center text-danger">
                                Превышен лимит ожидания ответа от сервера
                                <button class="btn btn-sm btn-outline-primary ms-2 retry-button">Повторить</button>
                            </td></tr>`);
                        }
                    } else if (xhr.status === 401) {
                        // Ошибка авторизации
                        $('#reports-table tbody').html(`<tr><td colspan="5" class="text-center text-danger">
                            Ошибка авторизации. Необходимо повторно войти в систему.
                        </td></tr>`);
                        
                        // Можно добавить перенаправление на страницу входа
                        // window.location.href = 'login.php';
                    } else if (xhr.status === 0) {
                        // Ошибка соединения
                        if (retryCount < maxRetries) {
                            retryCount++;
                            setTimeout(executeRequest, retryDelay * retryCount);
                        } else {
                            $('#reports-table tbody').html(`<tr><td colspan="5" class="text-center text-danger">
                                Отсутствует соединение с сервером
                                <button class="btn btn-sm btn-outline-primary ms-2 retry-button">Повторить</button>
                            </td></tr>`);
                        }
                    } else {
                        // Другие ошибки
                        if (retryCount < maxRetries) {
                            retryCount++;
                            setTimeout(executeRequest, retryDelay * retryCount);
                        } else {
                            $('#reports-table tbody').html(`<tr><td colspan="5" class="text-center text-danger">
                                Ошибка при загрузке данных (${xhr.status})
                                <button class="btn btn-sm btn-outline-primary ms-2 retry-button">Повторить</button>
                            </td></tr>`);
                        }
                    }
                    
                    // Общий обработчик для кнопки повторной попытки
                    $('.retry-button').on('click', function() {
                        retryCount = 0;
                        loadReports(page, limit);
                    });
                }
            });
        }
        
        // Запускаем запрос
        executeRequest();
    }
    
    // Функция для просмотра отчета
    function viewReport(reportId) {
        const token = getToken();
        
        if (!token) {
            showMessage('error', 'Ошибка авторизации. Пожалуйста, войдите в систему заново.');
            return;
        }
        
        // Сохраняем ID отчета в переменной
        currentReportId = reportId;
        
        // Очищаем содержимое модального окна и показываем индикатор загрузки
        $('#report-content').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
        
        // Открываем модальное окно
        $('#reportModal').modal('show');
        
        // Функция для выполнения запроса с механизмом повторных попыток
        function executeRequest(retryCount = 0, delay = 500) {
            $.ajax({
                url: 'api/reports.php?id=' + reportId,
                type: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token
                },
                timeout: 15000, // 15 секунд таймаут
                success: function(response) {
                    if (response.status === 'success') {
                        const report = response.data;
                        const reportData = report.report_data;
                        
                        // Формируем HTML для отображения отчета
                        let html = '';
                        
                        // Информация о компании
                        if (reportData.company_info) {
                            html += '<div class="card mb-4">';
                            html += '<div class="card-header d-flex justify-content-between align-items-center">';
                            html += '<h5 class="mb-0">Информация о компании</h5>';
                            
                            // Статус компании
                            let statusClass = 'bg-danger';
                            if (reportData.company_info.status === 'Действующий') {
                                statusClass = 'bg-success';
                            } else if (reportData.company_info.status && reportData.company_info.status.includes('ликвидации')) {
                                statusClass = 'bg-warning';
                            }
                            
                            html += '<span class="badge ' + statusClass + '">' + (reportData.company_info.status || 'Нет данных') + '</span>';
                            html += '</div>';
                            html += '<div class="card-body">';
                            html += '<div class="row">';
                            html += '<div class="col-md-6">';
                            html += '<p><strong>УНП:</strong> ' + (reportData.company_info.unp || 'Нет данных') + '</p>';
                            html += '<p><strong>Полное наименование:</strong> ' + (reportData.company_info.name || 'Нет данных') + '</p>';
                            html += '<p><strong>Краткое наименование:</strong> ' + (reportData.company_info.short_name || 'Нет данных') + '</p>';
                            html += '</div>';
                            html += '<div class="col-md-6">';
                            html += '<p><strong>Адрес:</strong> ' + (reportData.company_info.address || 'Нет данных') + '</p>';
                            html += '<p><strong>Дата регистрации:</strong> ' + (reportData.company_info.registration_date || 'Нет данных') + '</p>';
                            html += '<p><strong>Инспекция МНС:</strong> ' + (reportData.company_info.tax_office || 'Нет данных') + '</p>';
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                        } else {
                            html += '<div class="alert alert-warning">Информация о компании отсутствует</div>';
                        }
                        
                        // Рейтинг надежности
                        if (reportData.reliability_rating) {
                            const score = reportData.reliability_rating.score;
                            const level = reportData.reliability_rating.level;
                            
                            html += '<div class="card mb-4">';
                            html += '<div class="card-header">';
                            html += '<h5 class="mb-0">Рейтинг надежности</h5>';
                            html += '</div>';
                            html += '<div class="card-body">';
                            html += '<div class="row align-items-center">';
                            html += '<div class="col-md-4 text-center">';
                            
                            // Определяем класс для круга рейтинга
                            let ratingClass = 'low-reliability';
                            if (level === 'high') {
                                ratingClass = 'high-reliability';
                            } else if (level === 'medium') {
                                ratingClass = 'medium-reliability';
                            }
                            
                            html += '<div class="rating-circle ' + ratingClass + '">';
                            html += '<div class="inner">';
                            html += '<div class="rating-score">' + score + '</div>';
                            html += '<div class="rating-text">из 100</div>';
                            html += '</div>';
                            html += '</div>';
                            
                            // Добавляем текстовое описание рейтинга
                            let ratingText = '';
                            if (score >= 80) {
                                ratingText = 'Надежный контрагент';
                            } else if (score >= 50) {
                                ratingText = 'Средняя надежность';
                            } else {
                                ratingText = 'Низкая надежность';
                            }
                            html += '<div class="fw-semibold mt-2 fs-5">' + ratingText + '</div>';
                            
                            html += '</div>';
                            html += '<div class="col-md-8">';
                            html += '<h4 class="mb-3">Факторы, влияющие на рейтинг:</h4>';
                            
                            // Отображение факторов
                            reportData.reliability_rating.factors.forEach(function(factor) {
                                const factorName = translateFactorName(factor.name);
                                const factorScore = factor.score;
                                const factorWeight = factor.weight;
                                
                                // Определяем цвет прогресс-бара в зависимости от оценки фактора
                                let barColorClass = 'bg-danger';
                                if (factorScore >= 80) {
                                    barColorClass = 'bg-success';
                                } else if (factorScore >= 50) {
                                    barColorClass = 'bg-warning';
                                }
                                
                                html += '<div class="factor-bar">';
                                html += '<div class="factor-name">' + factorName + '</div>';
                                html += '<div class="progress">';
                                html += '<div class="progress-bar ' + barColorClass + '" role="progressbar" ';
                                html += 'style="width: ' + factorScore + '%" aria-valuenow="' + factorScore + '" ';
                                html += 'aria-valuemin="0" aria-valuemax="100">';
                                html += '<span class="factor-weight">' + factorWeight + '%</span>';
                                html += '</div>';
                                html += '</div>';
                                html += '</div>';
                            });
                            
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                        } else {
                            html += '<div class="alert alert-warning">Информация о рейтинге надежности отсутствует</div>';
                        }
                        
                        // Информация о ликвидации
                        html += '<div class="card mb-4">';
                        html += '<div class="card-header">';
                        html += '<h5 class="mb-0">Информация о ликвидации</h5>';
                        html += '</div>';
                        html += '<div class="card-body">';
                        
                        if (reportData.liquidation_info && reportData.liquidation_info.length > 0) {
                            html += '<div class="table-responsive">';
                            html += '<table class="table table-bordered table-striped">';
                            html += '<thead>';
                            html += '<tr>';
                            html += '<th>Дата решения</th>';
                            html += '<th>Дата ликвидации</th>';
                            html += '<th>Статус</th>';
                            html += '<th>Ликвидатор</th>';
                            html += '</tr>';
                            html += '</thead>';
                            html += '<tbody>';
                            
                            reportData.liquidation_info.forEach(function(item) {
                                html += '<tr>';
                                html += '<td>' + (formatDate(item.liquidationDecisionDate) || 'Нет данных') + '</td>';
                                html += '<td>' + (formatDate(item.liquidationDate) || 'Нет данных') + '</td>';
                                html += '<td>' + (getEntityStateText(item.entityState) || 'Нет данных') + '</td>';
                                html += '<td>' + (item.liquidatorName || 'Нет данных') + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody>';
                            html += '</table>';
                            html += '</div>';
                        } else {
                            html += '<p class="text-muted">Информация о ликвидации отсутствует.</p>';
                        }
                        
                        html += '</div>';
                        html += '</div>';
                        
                        // Отображаем контент
                        $('#report-content').html(html);
                    } else {
                        $('#report-content').html('<div class="alert alert-danger">' +
                            '<p>Ошибка при загрузке отчета: ' + (response.message || 'Неизвестная ошибка') + '</p>' +
                            '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Ошибка при загрузке отчета';
                    
                    // Если это не последняя попытка, пробуем еще раз
                    if (retryCount < 2) {
                        console.log('Попытка ' + (retryCount + 1) + ' не удалась. Повторная попытка через ' + delay + 'мс...');
                        
                        setTimeout(function() {
                            executeRequest(retryCount + 1, delay * 1.5);
                        }, delay);
                        return;
                    }
                    
                    // Обработка различных типов ошибок
                    if (status === 'timeout') {
                        errorMessage = 'Превышено время ожидания ответа от сервера';
                    } else if (xhr.status === 401) {
                        errorMessage = 'Ошибка авторизации. Пожалуйста, войдите в систему заново.';
                        // Перенаправляем на страницу входа
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 2000);
                    } else if (xhr.status === 404) {
                        errorMessage = 'Отчет не найден';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Доступ запрещен';
                    } else if (xhr.status >= 500) {
                        errorMessage = 'Ошибка сервера. Пожалуйста, попробуйте позже.';
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    
                    // Показываем сообщение об ошибке и кнопку для повторной попытки
                    $('#report-content').html('<div class="alert alert-danger">' +
                        '<p>' + errorMessage + '</p>' +
                        '<button type="button" class="btn btn-primary mt-2" onclick="viewReport(' + reportId + ')">' +
                        '<i class="bi bi-arrow-clockwise"></i> Повторить запрос</button>' +
                        '</div>');
                }
            });
        }
        
        // Запускаем выполнение запроса
        executeRequest();
    }
    
    // Функция для удаления отчета
    function deleteReport(reportId) {
        if (!confirm('Вы уверены, что хотите удалить отчет №' + reportId + '?')) {
            return;
        }
        
        const token = getToken();
        
        if (!token) {
            return;
        }
        
        // Выполняем запрос к API
        $.ajax({
            url: 'api/reports.php?id=' + reportId,
            type: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Перезагружаем список отчетов
                    loadReports(1);
                    
                    // Показываем сообщение об успешном удалении
                    showMessage('success', 'Отчет успешно удален');
                } else {
                    showMessage('error', 'Ошибка при удалении отчета: ' + response.message);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Ошибка при удалении отчета';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage += ': ' + xhr.responseJSON.message;
                }
                
                showMessage('error', errorMessage);
            }
        });
    }
    
    // Функция для форматирования даты
    function formatDate(dateString) {
        if (!dateString) return null;
        
        const date = new Date(dateString);
        
        if (isNaN(date.getTime())) {
            return dateString;
        }
        
        return date.toLocaleDateString('ru-RU') + ' ' + date.toLocaleTimeString('ru-RU');
    }
    
    // Функция для получения текстового представления статуса организации
    function getEntityStateText(state) {
        const states = {
            1: 'В процессе ликвидации',
            2: 'Ликвидирован',
            0: 'Действующий'
        };
        
        return states[state] || `Неизвестный статус (${state})`;
    }
    
    // Функция для перевода названий факторов
    function translateFactorName(factor) {
        const translations = {
            company_status: 'Статус юридического лица',
            company_age: 'Время существования компании',
            liquidation: 'Наличие процессов ликвидации',
            tax_registration: 'Регистрация в налоговой'
        };
        
        return translations[factor] || factor;
    }
    
    // Обработчики кнопок экспорта
    $('#export-pdf-modal-button').on('click', function() {
        const user = getCurrentUser();
        
        if (!user || (user.role !== 'administrator' && user.role !== 'analyst')) {
            showMessage('error', 'Для экспорта необходимо войти в систему с правами администратора или аналитика');
            return;
        }
        
        // Получаем report_id из текущего отчета
        const reportId = currentReportId;
        if (!reportId) {
            showMessage('error', 'Не удалось идентифицировать отчет');
            return;
        }
        
        // Показываем индикатор загрузки
        $('#loading').removeClass('d-none');
        
        // Отправляем запрос на экспорт
        $.ajax({
            url: 'api/export_pdf.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'Authorization': 'Bearer ' + getToken() },
            data: JSON.stringify({ report_id: reportId }),
            success: function(response) {
                // Скрываем индикатор загрузки
                $('#loading').addClass('d-none');
                
                if (response.status === 'success') {
                    // Создаем ссылку для скачивания файла
                    const downloadUrl = response.download_url;
                    
                    // Скачиваем файл
                    const link = document.createElement('a');
                    link.href = downloadUrl;
                    link.download = response.file;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showMessage('success', 'PDF успешно сформирован и скачан');
                } else {
                    showMessage('error', 'Ошибка при формировании отчета: ' + (response.message || 'неизвестная ошибка'));
                }
            },
            error: function(xhr) {
                // Скрываем индикатор загрузки
                $('#loading').addClass('d-none');
                
                // Показываем ошибку
                let errorMessage = 'Ошибка при формировании отчета';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                showMessage('error', errorMessage);
            }
        });
    });
});
</script>

<?php
include 'templates/footer.php';
?> 