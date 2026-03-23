<?php
$errorStatus = 404;
$errorTitle = 'Page not found';
$errorMessage = 'The page you requested does not exist or is no longer available.';
$errorContext = is_array($errorContext ?? null) ? $errorContext : [];
require __DIR__ . '/http.php';
