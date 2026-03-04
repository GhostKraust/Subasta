<?php
session_start();

if (empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["admin_role"])) {
    $_SESSION["admin_role"] = "admin";
}

function current_role()
{
    return $_SESSION["admin_role"] ?? "admin";
}

function is_admin()
{
    return current_role() === "admin";
}

function require_admin()
{
    if (!is_admin()) {
        http_response_code(403);
        exit("Acceso no autorizado.");
    }
}
