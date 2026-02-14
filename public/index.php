<?php
// Redirect to main entry point
// DEBUG ROUTING to stderr
error_log("Router: " . $_SERVER['REQUEST_URI']);
define('LP_PUBLIC_ENTRY', true);
require dirname(__DIR__) . '/index.php';
