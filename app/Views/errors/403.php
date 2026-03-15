<?php
$errorStatus = 403;
$errorTitle = 'Access denied';
$errorMessage = 'You do not have permission to access this area.';
$errorContext = is_array($errorContext ?? null) ? $errorContext : [];
require __DIR__ . '/http.php';
