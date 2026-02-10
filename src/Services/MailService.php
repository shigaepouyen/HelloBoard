<?php

class MailService {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $fromName;

    public function __construct($config) {
        $this->host = $config['smtpHost'] ?? 'smtp.gmail.com';
        $this->port = (int)($config['smtpPort'] ?? 587);
        $this->user = $config['smtpUser'] ?? '';
        $this->pass = $config['smtpPass'] ?? '';
        $this->fromName = $config['smtpFromName'] ?? 'HelloBoard';
    }

    public function send($to, $subject, $body, $vars = [], $trackingUrl = '') {
        // Replace variables
        foreach ($vars as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
        }

        // Inject tracking pixel if provided
        if ($trackingUrl) {
            $pixel = '<img src="' . $trackingUrl . '" width="1" height="1" style="display:none !important;" />';
            if (stripos($body, '</body>') !== false) {
                $body = str_ireplace('</body>', $pixel . '</body>', $body);
            } else {
                $body .= $pixel;
            }
        }

        // Basic HTML wrapper if it doesn't look like full HTML
        if (stripos($body, '<html') === false) {
            $body = '<!DOCTYPE html><html><body style="font-family: sans-serif; line-height: 1.6; color: #333;">' . nl2br($body) . '</body></html>';
        }

        return $this->smtpSend($to, $subject, $body);
    }

    private function smtpSend($to, $subject, $body) {
        $timeout = 10;
        $hostPrefix = ($this->port === 465) ? 'ssl://' : 'tcp://';
        $socket = stream_socket_client($hostPrefix . $this->host . ':' . $this->port, $errno, $errstr, $timeout);
        if (!$socket) throw new Exception("Connexion SMTP échouée: $errstr");

        $this->expect($socket, '220');

        $helloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $this->sendCmd($socket, "EHLO " . $helloHost);
        $this->expect($socket, '250');

        if ($this->port === 587) {
            $this->sendCmd($socket, "STARTTLS");
            $this->expect($socket, '220');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Échec de l'activation TLS");
            }
            $this->sendCmd($socket, "EHLO " . $helloHost);
            $this->expect($socket, '250');
        }

        if ($this->user && $this->pass) {
            $this->sendCmd($socket, "AUTH LOGIN");
            $this->expect($socket, '334');
            $this->sendCmd($socket, base64_encode($this->user));
            $this->expect($socket, '334');
            $this->sendCmd($socket, base64_encode($this->pass));
            $this->expect($socket, '235');
        }

        $this->sendCmd($socket, "MAIL FROM: <{$this->user}>");
        $this->expect($socket, '250');

        $this->sendCmd($socket, "RCPT TO: <$to>");
        $this->expect($socket, '250');

        $this->sendCmd($socket, "DATA");
        $this->expect($socket, '354');

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->user}>",
            "To: <$to>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "Message-ID: <" . time() . "." . uniqid() . "@" . $helloHost . ">"
        ];

        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
        $this->expect($socket, '250');

        $this->sendCmd($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private function sendCmd($socket, $cmd) {
        fwrite($socket, $cmd . "\r\n");
    }

    private function expect($socket, $code) {
        $response = "";
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] == ' ') break;
        }
        if (strpos($response, $code) !== 0) {
            throw new Exception("Erreur SMTP: attendu $code, reçu " . $response);
        }
    }
}
