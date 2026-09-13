<?php
/**
 * Application constants.
 * Central configuration for roles, statuses, routing rules, and paths.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Paths (safe regardless of how the app is included)
// ---------------------------------------------------------------------
define('DS', DIRECTORY_SEPARATOR);
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . DS . 'config');
define('INCLUDES_PATH', BASE_PATH . DS . 'includes');
define('PUBLIC_PATH', BASE_PATH . DS . 'public');
define('API_PATH', BASE_PATH . DS . 'api');

// ---------------------------------------------------------------------
// Web-accessible base URL.
// Resolved to the project root (e.g. http://192.168.1.100/document-monitoring)
// by matching the on-disk project path against DOCUMENT_ROOT, so BASE_URL is
// stable no matter which sub-folder a page lives in.
// ---------------------------------------------------------------------
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $baseUrl = $scheme . '://' . $host;
    $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRoot && strpos(BASE_PATH, $docRoot) === 0) {
        $rel = trim(str_replace('\\', '/', substr(BASE_PATH, strlen($docRoot))), '/');
        if ($rel !== '') {
            $baseUrl .= '/' . $rel;
        }
    } else {
        // Fallback: walk up from the current script's directory, stopping when we
        // no longer sit inside a known application sub-folder.
        $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
        $pieces = array_values(array_filter(explode('/', trim($dir, '/'))));
        $knownSub = ['api', 'admin', 'branch', 'secretary_deputy', 'secretary_co', 'reports', 'includes', 'config'];
        while ($pieces && in_array(end($pieces), $knownSub, true)) {
            array_pop($pieces);
        }
        $rel = implode('/', $pieces);
        if ($rel !== '') {
            $baseUrl .= '/' . $rel;
        }
    }
    define('BASE_URL', $baseUrl);
}

// ---------------------------------------------------------------------
// Roles
// ---------------------------------------------------------------------
define('ROLE_BRANCH',          'BRANCH');
define('ROLE_SECRETARY_DEPUTY','SECRETARY_DEPUTY');
define('ROLE_SECRETARY_CO',    'SECRETARY_CO');
define('ROLE_ADMIN',           'ADMIN');

// ---------------------------------------------------------------------
// Document statuses
// ---------------------------------------------------------------------
const DOC_STATUSES = ['NEW', 'RECEIVED', 'PENDING', 'SIGNED', 'APPROVED', 'RTS'];
const STATUS_PENDING  = 'PENDING';
const STATUS_SIGNED   = 'SIGNED';
const STATUS_APPROVED = 'APPROVED';
const STATUS_RTS      = 'RTS';

// ---------------------------------------------------------------------
// Tracking actions
// ---------------------------------------------------------------------
const ACTION_CREATED   = 'CREATED';
const ACTION_RECEIVED  = 'RECEIVED';
const ACTION_FORWARDED = 'FORWARDED';
const ACTION_STATUS    = 'STATUS_CHANGED';
const ACTION_RTS       = 'RETURNED_TO_SENDER';
const ACTION_SIGNED    = 'SIGNED';
const ACTION_APPROVED  = 'APPROVED';

// ---------------------------------------------------------------------
// ROUTING MATRIX
// Which roles a given role may route a document TO.
// Admin is deliberately absent from this matrix.
// ---------------------------------------------------------------------
function route_targets(string $fromRole): array
{
    switch ($fromRole) {
        case ROLE_BRANCH:
            // Branch may send to another Branch, Secretary Deputy, or Secretary of CO.
            return [ROLE_BRANCH, ROLE_SECRETARY_DEPUTY, ROLE_SECRETARY_CO];
        case ROLE_SECRETARY_DEPUTY:
            return [ROLE_BRANCH, ROLE_SECRETARY_CO];
        case ROLE_SECRETARY_CO:
            return [ROLE_BRANCH, ROLE_SECRETARY_DEPUTY];
        default:
            return []; // Admin, unknown roles cannot route.
    }
}

/**
 * Returns true if $fromRole is allowed to route to $toRole.
 */
function can_route(string $fromRole, string $toRole): bool
{
    return in_array($toRole, route_targets($fromRole), true);
}
