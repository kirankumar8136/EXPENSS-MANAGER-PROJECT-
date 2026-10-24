<?php
/**
 * SmartSpend Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logoutUser();
flash('info', 'You have been securely logged out.');
redirect('login.php');
