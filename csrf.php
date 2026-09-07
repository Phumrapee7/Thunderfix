<?php
// CSRF protection helpers — include after session_start() on any page with a POST form.

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Echo this inside every <form method="POST">.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return $token !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Call as the first thing inside every POST handler. Stops the request on mismatch.
function csrf_require(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง (CSRF token ไม่ตรงหรือหมดอายุ) กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}
