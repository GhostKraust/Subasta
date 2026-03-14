<?php

function is_https_request()
{
    if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""));
    return $forwardedProto === "https";
}

function send_security_headers()
{
    if (headers_sent()) {
        return;
    }

    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=()", false);
    header("Cross-Origin-Resource-Policy: same-origin");
    header(
        "Content-Security-Policy: default-src 'self' https: data: blob: 'unsafe-inline' 'unsafe-eval'; " .
        "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'"
    );

    if (is_https_request()) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

function start_secure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.cookie_httponly", "1");

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => is_https_request(),
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}

function csrf_token()
{
    start_secure_session();
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function csrf_input()
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8");
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_token($token)
{
    start_secure_session();
    $stored = $_SESSION["csrf_token"] ?? "";
    return is_string($token) && $stored !== "" && hash_equals($stored, $token);
}
