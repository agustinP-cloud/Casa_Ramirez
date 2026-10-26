<?php
session_start();
require_once __DIR__ . '/../config/conexion.php';

function requerir_login() {
    if (!isset($_SESSION['id_usuario'])) {
        header('Location: login.php');
        exit;
    }
}

function requerir_rol(array $roles) {
    requerir_login();
    if (!in_array($_SESSION['rol'], $roles)) {
        http_response_code(403);
        die('No tenés permiso para entrar a esta página.');
    }
}

// Escapa texto para mostrarlo seguro en HTML
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}