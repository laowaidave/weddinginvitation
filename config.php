<?php
/**
 * config.php — core loader and shared helpers.
 *
 * There is nothing to edit in this file. All of the site's settings
 * (wedding details, the hashed admin password and the guest list) live in
 * data/settings.php, which is created and updated by the setup wizard
 * (setup.php). This file simply loads those settings and provides the
 * helper functions used by index.php (the public invitation) and
 * admin.php (the private dashboard).
 */

// ---------------------------------------------------------------------------
// Filesystem paths — everything is resolved relative to this file, so the app
// works no matter which folder it is uploaded into.
// ---------------------------------------------------------------------------
define('APP_DIR',       __DIR__);
define('DATA_DIR',      __DIR__ . '/data');       // private storage (blocked from the web)
define('UPLOAD_DIR',    __DIR__ . '/uploads');    // public storage (the couple's photo)
define('SETTINGS_FILE', DATA_DIR . '/settings.php');
define('RSVP_FILE',     DATA_DIR . '/rsvps.php');

// ---------------------------------------------------------------------------
// e() — shorthand for safely escaping text before printing it into HTML.
// ---------------------------------------------------------------------------
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Settings: load and save the site configuration.
// ---------------------------------------------------------------------------

/** True once the setup wizard has been completed at least once. */
function is_configured(): bool {
    if (!is_file(SETTINGS_FILE)) return false;
    $s = load_settings();
    return !empty($s['setup_complete']);
}

/** Return the settings array (empty array if the site is not set up yet). */
function load_settings(): array {
    if (!is_file(SETTINGS_FILE)) return [];
    $s = @include SETTINGS_FILE;
    return is_array($s) ? $s : [];
}

/** Write the settings array back to disk. Returns true on success. */
function save_settings(array $settings): bool {
    ensure_dir(DATA_DIR);
    $php = "<?php\n"
         . "// Site settings — written by the setup wizard (setup.php).\n"
         . "// You can edit values here by hand, but it is easier to use /setup.\n"
         . "return " . var_export($settings, true) . ";\n";
    return atomic_write(SETTINGS_FILE, $php);
}

// ---------------------------------------------------------------------------
// RSVP storage: each response is one record appended to data/rsvps.php.
// ---------------------------------------------------------------------------

/** Return every stored RSVP (oldest first). */
function load_rsvps(): array {
    if (!is_file(RSVP_FILE)) return [];
    $r = @include RSVP_FILE;
    return is_array($r) ? $r : [];
}

/** Append one RSVP record. Returns true on success. */
function save_rsvp(array $record): bool {
    ensure_dir(DATA_DIR);
    $rows   = load_rsvps();
    $rows[] = $record;
    $php = "<?php\n// RSVP submissions — auto-generated, do not edit by hand.\n"
         . "return " . var_export($rows, true) . ";\n";
    return atomic_write(RSVP_FILE, $php);
}

// ---------------------------------------------------------------------------
// Small filesystem helpers.
// ---------------------------------------------------------------------------

/** Create a directory (and parents) if it does not already exist. */
function ensure_dir(string $dir): void {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

/** Write a file safely: write to a temp file first, then rename into place. */
function atomic_write(string $path, string $contents): bool {
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $contents, LOCK_EX) === false) return false;
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

/** Generate a new, hard-to-guess invitation token. */
function make_token(): string {
    return bin2hex(random_bytes(10)); // 20 hex characters
}

// ---------------------------------------------------------------------------
// Admin authentication (used by admin.php and by setup.php once configured).
// The password itself is never stored — only a secure hash of it.
// ---------------------------------------------------------------------------

/** Start the admin session exactly once per request. */
function admin_session_start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('wed_admin');
        session_start();
    }
}

/** True if the current visitor has signed in as the admin. */
function admin_is_authed(): bool {
    admin_session_start();
    return !empty($_SESSION['admin_ok']);
}

/** Check a password against the stored hash and, if correct, sign in. */
function admin_login(string $password): bool {
    $hash = load_settings()['admin_hash'] ?? '';
    if ($hash !== '' && password_verify($password, $hash)) {
        admin_session_start();
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        return true;
    }
    return false;
}

/** Sign the admin out. */
function admin_logout(): void {
    admin_session_start();
    $_SESSION = [];
    session_destroy();
}

// ---------------------------------------------------------------------------
// Convenience: read one setting with a fallback default.
// ---------------------------------------------------------------------------
function setting(string $key, $default = '') {
    $s = load_settings();
    return $s[$key] ?? $default;
}
