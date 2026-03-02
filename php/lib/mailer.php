<?php
function smtp_read_response($socket)
{
    $response = "";
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === " ") {
            break;
        }
    }
    return $response;
}

function smtp_send_command($socket, $command)
{
    fwrite($socket, $command . "\r\n");
    return smtp_read_response($socket);
}

function send_smtp_mail($host, $port, $from, $to, $subject, $body)
{
    $socket = fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        return ["ok" => false, "error" => "No se pudo conectar al SMTP: " . $errstr];
    }

    $greeting = smtp_read_response($socket);
    if (strpos($greeting, "220") !== 0) {
        fclose($socket);
        return ["ok" => false, "error" => "Respuesta SMTP invalida: " . trim($greeting)];
    }

    $helo = smtp_send_command($socket, "HELO localhost");
    if (strpos($helo, "250") !== 0) {
        fclose($socket);
        return ["ok" => false, "error" => "HELO fallo: " . trim($helo)];
    }

    $mailFrom = smtp_send_command($socket, "MAIL FROM:<" . $from . ">");
    if (strpos($mailFrom, "250") !== 0) {
        fclose($socket);
        return ["ok" => false, "error" => "MAIL FROM fallo: " . trim($mailFrom)];
    }

    $rcptTo = smtp_send_command($socket, "RCPT TO:<" . $to . ">");
    if (strpos($rcptTo, "250") !== 0) {
        fclose($socket);
        return ["ok" => false, "error" => "RCPT TO fallo: " . trim($rcptTo)];
    }

    $data = smtp_send_command($socket, "DATA");
    if (strpos($data, "354") !== 0) {
        fclose($socket);
        return ["ok" => false, "error" => "DATA fallo: " . trim($data)];
    }

    $headers = [];
    $headers[] = "From: " . $from;
    $headers[] = "To: " . $to;
    $headers[] = "Subject: " . $subject;
    $headers[] = "Date: " . date("r");
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    fwrite($socket, $message . "\r\n.\r\n");
    $sent = smtp_read_response($socket);
    smtp_send_command($socket, "QUIT");
    fclose($socket);

    if (strpos($sent, "250") !== 0) {
        return ["ok" => false, "error" => "Envio fallo: " . trim($sent)];
    }

    return ["ok" => true, "error" => ""];
}
