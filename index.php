<?php
// Главная страница приложения
session_start();

// Подключение необходимых файлов
require_once 'config/config.php';
require_once 'includes/functions.php';

// Рендеринг главной страницы
include 'templates/header.php';
include 'templates/main.php';
include 'templates/footer.php';
?> 