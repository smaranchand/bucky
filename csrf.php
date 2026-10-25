<?php
/**
 * CSRF token generation and validation.
 */

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_validate(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return Bucky\Csrf::matches($_SESSION['csrf_token'] ?? null, $_POST['csrf_token'] ?? null);
}
