<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";

admin_start_session();
session_destroy();
header("Location: login.php");
exit;

