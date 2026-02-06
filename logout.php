<?php
require_once 'includes/config.php';

// تخریب session
session_start();
session_destroy();

// ریدایرکت به صفحه login
header('Location: login.php');
exit();
?>