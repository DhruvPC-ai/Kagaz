<?php
require __DIR__ . '/includes/init.php';

$_SESSION = [];
session_destroy();
session_start();
session_regenerate_id(true);
flash('success', 'You have logged out.');
redirect('index.php');
