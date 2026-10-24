<?php
/**
 * SmartSpend Application Entry Point
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
if (empty($user['onboarding_completed'])) {
    redirect('onboarding.php');
}

redirect('dashboard.php');
