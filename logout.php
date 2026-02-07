<?php
// logout.php
require_once 'includes/session.php';

// Destroy the session and redirect to login
destroySession();

// Redirect to login page with a success message
header("Location: login.php?logged_out=1");
exit();
?>