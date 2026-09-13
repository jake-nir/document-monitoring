<?php
/**
 * Application bootstrap. Loads configuration, helpers, and starts session.
 * Include this at the top of every page.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once CONFIG_PATH . DIRECTORY_SEPARATOR . 'database.php';
require_once CONFIG_PATH . DIRECTORY_SEPARATOR . 'auth.php';
require_once INCLUDES_PATH . DIRECTORY_SEPARATOR . 'functions.php';
require_once INCLUDES_PATH . DIRECTORY_SEPARATOR . 'layout.php';
