<?php
declare(strict_types=1);

final class SmtpMailer
{
    private array $config;
    private $socket = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $message): void
    {
        $this->connect();

        try {
            $this->command('EHLO ' . $this->heloHost(), [250]);

            if ($this->encryption() === 'tls') {
                $this->command('STARTTLS', [220]);

                $cryptoEnabled = @stream_socket_enable_crypto(
                    $this->socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );

                if ($cryptoEnabled !== true) {
                    throw new RuntimeException('Could not enable TLS encryption for SMTP.');
                }

                $this->command('EHLO ' . $this->heloHost(), [250]);
            }

            if (config_bool($this->config['auth'] ?? true)) {
                $this->authenticate();
            }

            $fromEmail = (string) ($message['from_email'] ?? '');
            $toEmail = (string) ($message['to_email'] ?? '');

            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', [354]);
            $this->write($this->buildMimeMessage($message) . "\r\n.");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        $host = trim((string) ($this->config['host'] ?? ''));
        $port = (int) ($this->config['port'] ?? 587);

        if ($host === '' || $port <= 0) {
            throw new RuntimeException('SMTP host or port is missing.');
        }

        $transport = $this->encryption() === 'ssl' ? 'ssl://' : '';
        $timeout = (float) ($this->config['timeout'] ?? 20);
        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr);
        }

        stream_set_timeout($this->socket, (int) ceil($timeout));
        $this->expect([220]);
    }

    private function authenticate(): void
    {
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new RuntimeException('SMTP authentication is enabled, but username or password is missing.');
        }

        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($username), [334]);
        $this->command(base64_encode($password), [235]);
    }

    private function buildMimeMessage(array $message): string
    {
        $boundary = 'a1tech-' . bin2hex(random_bytes(12));
        $fromName = $this->encodeHeader((string) ($message['from_name'] ?? 'A1 Technovation'));
        $fromEmail = (string) ($message['from_email'] ?? '');
        $toEmail = (string) ($message['to_email'] ?? '');
        $toName = $this->encodeHeader((string) ($message['to_name'] ?? ''));
        $subject = $this->encodeHeader((string) ($message['subject'] ?? 'Website enquiry'));
        $html = (string) ($message['html'] ?? '');
        $text = (string) ($message['text'] ?? strip_tags($html));

        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s O'),
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'To: ' . $this->formatAddress($toEmail, $toName),
            'Subject: ' . $subject,
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->heloHost() . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        if (!empty($message['reply_to_email'])) {
            $headers[] = 'Reply-To: ' . $this->formatAddress(
                (string) $message['reply_to_email'],
                $this->encodeHeader((string) ($message['reply_to_name'] ?? ''))
            );
        }

        $body = [
            'This is a multi-part message in MIME format.',
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($text)),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html)),
            '--' . $boundary . '--',
        ];

        return implode("\r\n", array_merge($headers, [''], $body));
    }

    private function formatAddress(string $email, string $name = ''): string
    {
        return $name !== '' ? $name . ' <' . $email . '>' : '<' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function command(string $command, array $expectedCodes): void
    {
        $this->write($command);
        $this->expect($expectedCodes);
    }

    private function write(string $command): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP socket is not connected.');
        }

        $payload = $command . "\r\n";
        $result = fwrite($this->socket, $payload);

        if ($result === false) {
            throw new RuntimeException('Failed to write to SMTP socket.');
        }
    }

    private function expect(array $expectedCodes): void
    {
        $response = $this->readResponse();
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    }

    private function readResponse(): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP socket is not connected.');
        }

        $response = '';

        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;

            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('Empty response from SMTP server.');
        }

        return $response;
    }

    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    private function encryption(): string
    {
        return strtolower(trim((string) ($this->config['encryption'] ?? 'tls')));
    }

    private function heloHost(): string
    {
        $configured = trim((string) ($this->config['helo_host'] ?? ''));

        if ($configured !== '') {
            return $configured;
        }

        $appHost = parse_url((string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            return $appHost;
        }

        return 'localhost';
    }
}
