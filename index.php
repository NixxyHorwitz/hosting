<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/api_helper.php';
require_once __DIR__ . '/core/mailer.php';
require_once __DIR__ . '/library/traffic.php';

// Track all public traffic (skip admin routes)
$_route_check = $_GET['route'] ?? '';
if (!str_starts_with($_route_check, 'admin')) {
    track_traffic($conn);
}


$route = isset($_GET['route']) ? rtrim($_GET['route'], '/') : '';
if ($route === '' || $route === 'home' || $route === 'index') {
    require __DIR__ . '/front/index.php';
    exit();
}

$segments = explode('/', $route);
$base_path = __DIR__;
$target_file = '';
$params = [];

// Try to match up to 3 deep: /feature/action/subaction
if (count($segments) >= 3 && file_exists($base_path . '/' . $segments[0] . '/' . $segments[1] . '/' . $segments[2] . '.php')) {
    $target_file = $base_path . '/' . $segments[0] . '/' . $segments[1] . '/' . $segments[2] . '.php';
    $params = array_slice($segments, 3);
} elseif (count($segments) >= 3 && file_exists($base_path . '/' . $segments[0] . '/' . $segments[1] . '/' . $segments[2] . '/index.php')) {
    $target_file = $base_path . '/' . $segments[0] . '/' . $segments[1] . '/' . $segments[2] . '/index.php';
    $params = array_slice($segments, 3);
} elseif (count($segments) >= 2 && file_exists($base_path . '/' . $segments[0] . '/' . $segments[1] . '.php')) {
    $target_file = $base_path . '/' . $segments[0] . '/' . $segments[1] . '.php';
    $params = array_slice($segments, 2);
} elseif (count($segments) >= 2 && file_exists($base_path . '/' . $segments[0] . '/' . $segments[1] . '/index.php')) {
    $target_file = $base_path . '/' . $segments[0] . '/' . $segments[1] . '/index.php';
    $params = array_slice($segments, 2);
} elseif (count($segments) >= 1 && file_exists($base_path . '/' . $segments[0] . '.php')) {
    $target_file = $base_path . '/' . $segments[0] . '.php';
    $params = array_slice($segments, 1);
} elseif (count($segments) >= 1 && file_exists($base_path . '/' . $segments[0] . '/index.php')) {
    $target_file = $base_path . '/' . $segments[0] . '/index.php';
    $params = array_slice($segments, 1);
}

if ($target_file) {
    // Expose parameters globally
    $_GET['params'] = $params;
    if (isset($params[0])) $_GET['id'] = $params[0];
    require $target_file;

} else {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1>";
    echo "<p>The requested URL was not found on this server.</p>";
}