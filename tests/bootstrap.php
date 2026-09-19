<?php

/**
 * PHPUnit bootstrap.
 *
 * Why this file exists: on Windows/MSYS (Git Bash), the shell can export
 * Laravel-ish variables (DB_CONNECTION, DB_DATABASE, CACHE_STORE,
 * SESSION_PATH, …) into the process environment. Three hazards follow:
 *
 *  1. phpunit.xml <env> entries fill $_ENV/getenv — never $_SERVER — and
 *     Laravel's Env (phpdotenv) reads $_SERVER FIRST, so a shell export
 *     silently beats the test configuration (this once pointed the suite
 *     at the dev database and its file cache).
 *  2. MSYS rewrites POSIX-looking values (a lone «/» for SESSION_PATH)
 *     into Windows paths («C:/Program Files/Git/») before the native php
 *     binary sees them, which then breaks Symfony cookie handling.
 *  3. The Dotenv repository is immutable, so an already-set entry is
 *     never re-read from .env — dropping one layer alone is not enough.
 *
 * For the isolation-critical variables below:
 * - entries planted by phpunit.xml (<env force> fills $_ENV, <server>
 *   fills both): drop the $_SERVER twin so the phpunit value in $_ENV is
 *   the sole source;
 * - everything else: drop BOTH superglobals and remove the real
 *   environment entry so .env (loaded later by the framework) fills them
 *   with clean, unmangled values.
 */
$monitored = [
    // Database
    'DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_PORT', 'DB_USERNAME',
    'DB_PASSWORD', 'DB_URL', 'DB_SOCKET',
    // Cache / queue / session / broadcast
    'CACHE_STORE', 'CACHE_PREFIX', 'CACHE_DRIVER', 'QUEUE_CONNECTION',
    'SESSION_DRIVER', 'SESSION_PATH', 'SESSION_DOMAIN', 'SESSION_SECURE',
    'SESSION_HTTP_ONLY', 'SESSION_LIFETIME', 'BROADCAST_CONNECTION',
    // Application
    'APP_ENV', 'APP_URL', 'APP_KEY', 'APP_DEBUG', 'APP_LOCALE',
    'BCRYPT_ROUNDS', 'MAIL_MAILER', 'FILESYSTEM_DISK',
    // Packages
    'SANCTUM_STATEFUL_DOMAINS', 'RBAC_CACHE_STORE', 'PULSE_ENABLED',
    'TELESCOPE_ENABLED', 'NIGHTWATCH_ENABLED',
];

// Mirrors the <server> entries in phpunit.xml — those must survive in
// $_ENV because phpunit planted the authoritative value there.
$phpunitPlanted = [
    'APP_ENV', 'BROADCAST_CONNECTION', 'CACHE_STORE', 'RBAC_CACHE_STORE',
    'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'MAIL_MAILER',
    'QUEUE_CONNECTION', 'SESSION_DRIVER',
];

foreach ($_SERVER as $key => $value) {
    if (! in_array($key, $monitored, true) || ! is_string($value)) {
        continue;
    }

    if (in_array($key, $phpunitPlanted, true)) {
        // Keep the phpunit value in $_ENV, drop the $_SERVER twin, and
        // sync the real environment block (getenv/putenv) to it.
        unset($_SERVER[$key]);

        if (isset($_ENV[$key]) && getenv($key) !== $_ENV[$key]) {
            putenv($key.'='.$_ENV[$key]);
        }

        continue;
    }

    // Drop every layer so Dotenv's immutable repository re-reads the
    // value from .env after the framework boots. putenv($key) without
    // «=» removes the entry from the real environment block, which the
    // array unsets cannot reach.
    unset($_SERVER[$key], $_ENV[$key]);
    putenv($key);
}

require __DIR__.'/../vendor/autoload.php';
