<?php
// logout.php
require_once 'includes/session.php';

destroySession();
header("Location: login.php");
exit();
?>