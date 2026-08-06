<?php
/**
 * Persistent PHP session: stays valid until the user clicks Logout.
 * Include this (once) instead of calling session_start() directly.
 */
if (session_status() === PHP_SESSION_NONE) {
    // ~10 years — effectively never expires from idle/timeout
    $lifetime = 60 * 60 * 24 * 365 * 10;

    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.cookie_lifetime', (string) $lifetime);
    // Prevent PHP GC from deleting long-lived session files
    ini_set('session.gc_probability', '0');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params($lifetime, '/');
    }

    session_start();
}
