<?php
session_start();
unset($_SESSION['admin_id']);
unset($_SESSION['admin_ten']);
header('Location: /nhasach/admin/login.php');
exit;
