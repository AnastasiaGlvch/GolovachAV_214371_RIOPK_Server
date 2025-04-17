<?php
// Страница управления пользователями
session_start();

// Подключение необходимых файлов
require_once 'config/config.php';
require_once 'includes/functions.php';

// Рендеринг страницы
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Управление пользователями</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Главная</a></li>
                <li class="breadcrumb-item active" aria-current="page">Управление пользователями</li>
            </ol>
        </nav>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="alert alert-info rounded-3 shadow-sm auth-required d-none">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
                    <p class="mb-0">Для управления пользователями необходимо войти в систему с правами администратора.</p>
                </div>
            </div>
            
            <div class="admin-container auth-only administrator-only d-none">
                <div class="mb-4">
                    <button class="btn btn-primary rounded-pill shadow-sm" id="add-user-button" data-bs-toggle="modal" data-bs-target="#userModal">
                        <i class="fas fa-user-plus me-2"></i> Добавить пользователя
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя пользователя</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Данные будут загружены через AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления/редактирования пользователя -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Добавление пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger rounded-3 d-none" id="user-form-error"></div>
                <form id="user-form">
                    <input type="hidden" id="user-id">
                    <div class="mb-3">
                        <label for="new-username" class="form-label">Имя пользователя</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-primary text-opacity-75"></i></span>
                            <input type="text" class="form-control border-start-0" id="new-username" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-primary text-opacity-75"></i></span>
                            <input type="email" class="form-control border-start-0" id="email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Пароль</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-primary text-opacity-75"></i></span>
                            <input type="password" class="form-control border-start-0" id="password">
                        </div>
                        <div class="form-text" id="password-help">При редактировании оставьте поле пустым, если не хотите менять пароль.</div>
                    </div>
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Роль</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-user-tag text-primary text-opacity-75"></i></span>
                            <select class="form-select border-start-0" id="role_id" required>
                                <!-- Роли будут загружены через AJAX -->
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="active" checked>
                        <label class="form-check-label" for="active">Активен</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary rounded-pill" id="save-user-button">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Проверка авторизации
    const user = getCurrentUser();
    
    if (!user || user.role !== 'administrator') {
        // Пользователь не авторизован или не имеет прав администратора
        $('.auth-required').removeClass('d-none');
    } else {
        // Пользователь авторизован и имеет права администратора
        $('.admin-container').removeClass('d-none');
        
        // Загружаем список пользователей
        loadUsers();
        
        // Загружаем список ролей
        loadRoles();
    }
    
    // Функция для загрузки списка пользователей
    function loadUsers() {
        const token = getToken();
        
        if (!token) {
            return;
        }
        
        // Очищаем таблицу
        $('#users-table tbody').empty();
        
        // Показываем индикатор загрузки
        $('#users-table tbody').html('<tr><td colspan="6" class="text-center">Загрузка данных...</td></tr>');
        
        // Выполняем запрос к API
        $.ajax({
            url: 'services/auth/api/users/index.php',
            type: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            success: function(response) {
                if (response.status === 'success') {
                    const users = response.data;
                    
                    // Очищаем таблицу
                    $('#users-table tbody').empty();
                    
                    if (users.length === 0) {
                        $('#users-table tbody').html('<tr><td colspan="6" class="text-center">Нет данных</td></tr>');
                        return;
                    }
                    
                    // Добавляем данные в таблицу
                    users.forEach(function(user) {
                        let row = '<tr>';
                        row += '<td>' + user.id + '</td>';
                        row += '<td>' + user.username + '</td>';
                        row += '<td>' + user.email + '</td>';
                        row += '<td>' + translateRole(user.role) + '</td>';
                        
                        // Статус пользователя
                        const activeStatus = user.active == 1 ? 
                            '<span class="badge bg-success">Активен</span>' : 
                            '<span class="badge bg-danger">Неактивен</span>';
                        row += '<td>' + activeStatus + '</td>';
                        
                        // Кнопки действий
                        row += '<td>';
                        row += '<button class="btn btn-sm btn-outline-primary rounded-pill edit-user-button me-1" data-id="' + user.id + '">';
                        row += '<i class="fas fa-edit me-1"></i> Изменить';
                        row += '</button>';
                        
                        // Защита от удаления текущего пользователя
                        if (user.id != getCurrentUser().id) {
                            row += '<button class="btn btn-sm btn-outline-danger rounded-pill delete-user-button" data-id="' + user.id + '">';
                            row += '<i class="fas fa-trash me-1"></i> Удалить';
                            row += '</button>';
                        }
                        
                        row += '</td>';
                        row += '</tr>';
                        
                        $('#users-table tbody').append(row);
                    });
                    
                    // Обработчик кнопок редактирования
                    $('.edit-user-button').on('click', function() {
                        const userId = $(this).data('id');
                        editUser(userId);
                    });
                    
                    // Обработчик кнопок удаления
                    $('.delete-user-button').on('click', function() {
                        const userId = $(this).data('id');
                        deleteUser(userId);
                    });
                } else {
                    $('#users-table tbody').html('<tr><td colspan="6" class="text-center text-danger">Ошибка при загрузке данных</td></tr>');
                }
            },
            error: function() {
                $('#users-table tbody').html('<tr><td colspan="6" class="text-center text-danger">Ошибка при загрузке данных</td></tr>');
            }
        });
    }
    
    // Функция для загрузки списка ролей
    function loadRoles() {
        const token = getToken();
        
        if (!token) {
            return;
        }
        
        // Очищаем список ролей
        $('#role_id').empty();
        
        // Выполняем запрос к API
        $.ajax({
            url: 'services/auth/api/roles/index.php',
            type: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            success: function(response) {
                if (response.status === 'success') {
                    const roles = response.data;
                    
                    // Добавляем роли в список
                    roles.forEach(function(role) {
                        $('#role_id').append('<option value="' + role.id + '">' + translateRole(role.name) + '</option>');
                    });
                }
            }
        });
    }
    
    // Обработчик кнопки "Добавить пользователя"
    $('#add-user-button').on('click', function() {
        // Очищаем форму
        $('#user-form')[0].reset();
        $('#user-id').val('');
        $('#password').prop('required', true);
        $('#password-help').addClass('d-none');
        
        // Меняем заголовок модального окна
        $('#userModalTitle').text('Добавление пользователя');
        
        // Скрываем ошибки
        $('#user-form-error').addClass('d-none');
    });
    
    // Функция для редактирования пользователя
    function editUser(userId) {
        const token = getToken();
        
        if (!token) {
            return;
        }
        
        // Выполняем запрос к API для получения данных пользователя
        $.ajax({
            url: 'services/auth/api/users/user.php?id=' + userId,
            type: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            success: function(response) {
                if (response.status === 'success') {
                    const userData = response.data;
                    
                    // Заполняем форму данными пользователя
                    $('#user-id').val(userData.id);
                    $('#new-username').val(userData.username);
                    $('#email').val(userData.email);
                    $('#role_id').val(getRoleIdByName(userData.role));
                    $('#active').prop('checked', userData.active == 1);
                    
                    // Пароль не требуется при редактировании
                    $('#password').val('').prop('required', false);
                    $('#password-help').removeClass('d-none');
                    
                    // Меняем заголовок модального окна
                    $('#userModalTitle').text('Редактирование пользователя');
                    
                    // Скрываем ошибки
                    $('#user-form-error').addClass('d-none');
                    
                    // Открываем модальное окно
                    $('#userModal').modal('show');
                } else {
                    showMessage('error', 'Ошибка при получении данных пользователя');
                }
            },
            error: function() {
                showMessage('error', 'Ошибка при получении данных пользователя');
            }
        });
    }
    
    // Функция для получения ID роли по имени
    function getRoleIdByName(roleName) {
        const roleSelect = $('#role_id');
        let roleId = '';
        
        // Перебираем все опции и ищем нужную
        roleSelect.find('option').each(function() {
            if ($(this).text().toLowerCase() === translateRole(roleName).toLowerCase()) {
                roleId = $(this).val();
                return false; // Прерываем цикл
            }
        });
        
        return roleId;
    }
    
    // Функция для удаления пользователя
    function deleteUser(userId) {
        if (!confirm('Вы уверены, что хотите удалить пользователя?')) {
            return;
        }
        
        const token = getToken();
        
        if (!token) {
            return;
        }
        
        // Выполняем запрос к API
        $.ajax({
            url: 'services/auth/api/users/user.php?id=' + userId,
            type: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Перезагружаем список пользователей
                    loadUsers();
                    
                    // Показываем сообщение об успешном удалении
                    showMessage('success', 'Пользователь успешно удален');
                } else {
                    showMessage('error', 'Ошибка при удалении пользователя: ' + response.message);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Ошибка при удалении пользователя';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage += ': ' + xhr.responseJSON.message;
                }
                
                showMessage('error', errorMessage);
            }
        });
    }
    
    // Обработчик кнопки "Сохранить" в модальном окне
    $('#save-user-button').on('click', function() {
        // Проверяем валидность формы
        if (!$('#user-form')[0].checkValidity()) {
            $('#user-form')[0].reportValidity();
            return;
        }
        
        const userId = $('#user-id').val();
        const isEdit = userId !== '';
        
        // Получаем значения из формы
        const username = $('#new-username').val().trim();
        const email = $('#email').val().trim();
        const roleId = $('#role_id').val();
        const active = $('#active').is(':checked') ? 1 : 0;
        const password = $('#password').val();
        
        // Формируем данные для отправки
        const userData = {
            username: username,
            email: email,
            role_id: roleId,
            active: active
        };
        
       
        if (password || !isEdit) {
            userData.password = password;
        }
        
        const token = getToken();
        
        if (!token) {
            return;
        }
        
       
        const url = isEdit ? 
            'services/auth/api/users/user.php?id=' + userId : 
            'services/auth/api/users/index.php';
        const method = isEdit ? 'PUT' : 'POST';
        
       
        $('#user-form-error').addClass('d-none').text('');
        
        // Выполняем запрос к API
        $.ajax({
            url: url,
            type: method,
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(userData),
            success: function(response) {
                if (response.status === 'success') {
                    // Закрываем модальное окно
                    $('#userModal').modal('hide');
                    
                    // Перезагружаем список пользователей
                    loadUsers();
                    
                    // Показываем сообщение об успешном сохранении
                    const message = isEdit ? 'Пользователь успешно обновлен' : 'Пользователь успешно создан';
                    showMessage('success', message);
                } else {
                    // Показываем ошибку в модальном окне
                    $('#user-form-error').removeClass('d-none').text(response.message);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Ошибка при сохранении пользователя';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                $('#user-form-error').removeClass('d-none').text(errorMessage);
            }
        });
    });
     // Функция для перевода названия роли
    function translateRole(role) {
        const translations = {
            administrator: 'Администратор',
            analyst: 'Аналитик',
            guest: 'Гость'
        };
        
        return translations[role] || role;
    }
});
</script>

<?php
include 'templates/footer.php';
?> 