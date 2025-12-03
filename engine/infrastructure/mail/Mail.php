<?php

/**
 * Клас для відправки email
 * Відправка листів через mail() або SMTP
 *
 * @package Engine\Classes\Mail
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

class Mail
{
    private string $to = '';
    private string $subject = '';
    private string $body = '';
    private array $headers = [];
    private array $attachments = [];
    private bool $isHtml = true;

    /**
     * Конструктор
     *
     * @param string|null $to Отримувач
     * @param string|null $subject Тема
     * @param string|null $body Тіло листа
     */
    public function __construct(?string $to = null, ?string $subject = null, ?string $body = null)
    {
        if ($to !== null) {
            $this->to($to);
        }
        if ($subject !== null) {
            $this->subject($subject);
        }
        if ($body !== null) {
            $this->body($body);
        }

        // Встановлюємо відправника з налаштувань, якщо доступно
        if (class_exists('SettingsManager')) {
            try {
                $adminEmail = settingsManager()->get('admin_email', '');
                if (! empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $this->from($adminEmail, 'Flowaxy CMS');
                }
            } catch (Exception $e) {
                // У разі помилки використовуємо стандартного відправника
            }
        }

        $this->header('X-Mailer', 'PHP/' . PHP_VERSION);
        $this->header('MIME-Version', '1.0');
    }

    /**
     * Встановлення отримувача
     *
     * @param string|array $to Отримувач (email або масив)
     * @return self
     */
    public function to($to): self
    {
        $this->to = is_array($to) ? implode(', ', $to) : $to;

        return $this;
    }

    /**
     * Встановлення теми
     *
     * @param string $subject Тема
     * @return self
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Встановлення тіла листа
     *
     * @param string $body Тіло листа
     * @param bool $isHtml HTML формат
     * @return self
     */
    public function body(string $body, bool $isHtml = true): self
    {
        $this->body = $body;
        $this->isHtml = $isHtml;

        return $this;
    }

    /**
     * Встановлення заголовка
     *
     * @param string $name Ім'я заголовка
     * @param string $value Значення
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Встановлення відправника
     *
     * @param string $email Email
     * @param string|null $name Ім'я
     * @return self
     */
    public function from(string $email, ?string $name = null): self
    {
        $this->header('From', $name !== null ? "{$name} <{$email}>" : $email);

        return $this;
    }

    /**
     * Встановлення копії
     *
     * @param string|array $cc Email або масив
     * @return self
     */
    public function cc($cc): self
    {
        $this->header('Cc', is_array($cc) ? implode(', ', $cc) : $cc);

        return $this;
    }

    /**
     * Встановлення прихованої копії
     *
     * @param string|array $bcc Email або масив
     * @return self
     */
    public function bcc($bcc): self
    {
        $this->header('Bcc', is_array($bcc) ? implode(', ', $bcc) : $bcc);

        return $this;
    }

    /**
     * Прикріплення файла
     *
     * @param string $filePath Шлях до файла
     * @param string|null $name Ім'я файла
     * @return self
     */
    public function attach(string $filePath, ?string $name = null): self
    {
        if (file_exists($filePath)) {
            $this->attachments[] = [
                'path' => $filePath,
                'name' => $name ?? basename($filePath),
            ];
        }

        return $this;
    }

    /**
     * Відправка листа
     *
     * @return bool
     */
    public function send(): bool
    {
        if (empty($this->to)) {
            return false;
        }

        return @mail($this->to, $this->subject, $this->buildBody(), $this->buildHeaders());
    }

    /**
     * Формування заголовків
     *
     * @return string
     */
    private function buildHeaders(): string
    {
        $this->header('Content-Type', $this->isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8');

        $headers = [];
        foreach ($this->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        return implode("\r\n", $headers);
    }

    /**
     * Формування тіла листа
     *
     * @return string
     */
    private function buildBody(): string
    {
        if (empty($this->attachments)) {
            return $this->body;
        }

        $boundary = '----=_NextPart_' . md5((string)time());
        $this->header('Content-Type', "multipart/mixed; boundary=\"{$boundary}\"");

        $body = "--{$boundary}\r\n";
        $body .= 'Content-Type: ' . ($this->isHtml ? 'text/html' : 'text/plain') . "; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $this->body . "\r\n\r\n";

        foreach ($this->attachments as $attachment) {
            $fileContent = @file_get_contents($attachment['path']);
            if ($fileContent === false) {
                continue;
            }

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/octet-stream; name=\"{$attachment['name']}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n\r\n";
            $body .= chunk_split(base64_encode($fileContent)) . "\r\n\r\n";
        }

        $body .= "--{$boundary}--";

        return $body;
    }

    /**
     * Встановлення HTML формату
     *
     * @param bool $isHtml
     * @return self
     */
    public function isHtml(bool $isHtml): self
    {
        $this->isHtml = $isHtml;

        return $this;
    }

    /**
     * Статичний метод: Швидка відправка листа
     *
     * @param string $to Отримувач
     * @param string $subject Тема
     * @param string $body Тіло листа
     * @param bool $isHtml HTML формат
     * @return bool
     */
    public static function sendQuick(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        return (new self($to, $subject, $body))->isHtml($isHtml)->send();
    }
}
