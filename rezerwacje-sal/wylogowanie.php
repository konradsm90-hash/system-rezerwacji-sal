<?php
require_once 'konfiguracja.php';
session_destroy();
header('Location: index.php');
exit;