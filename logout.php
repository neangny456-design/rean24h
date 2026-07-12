<?php
require_once __DIR__ . '/database.php';
session_unset();
session_destroy();
header('Location: index.php');
exit;
