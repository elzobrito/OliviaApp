<?php

namespace OliviaLib;

use Exception;

/**
 * Envio de e-mail via PHPMailer (dependência opcional: composer require phpmailer/phpmailer).
 *
 * Configuração SMTP lida do ambiente:
 *   OLIVIA_SMTP_HOST, OLIVIA_SMTP_PORT (587), OLIVIA_SMTP_USER, OLIVIA_SMTP_PASSWORD,
 *   OLIVIA_SMTP_FROM (padrão: OLIVIA_SMTP_USER), OLIVIA_SMTP_FROM_NAME,
 *   OLIVIA_SMTP_SECURE (tls|ssl|vazio, padrão tls).
 */
class Email
{
    private const PHPMAILER_CLASS = 'PHPMailer\\PHPMailer\\PHPMailer';

    private $email;
    private $mensagem;
    private $assunto;
    private $anexos;
    private $anexos2;

    function __construct($email, $mensagem, $assunto, $anexos = null, $anexos2 = null)
    {
        $this->email = $email;
        $this->mensagem = $mensagem;
        $this->assunto = $assunto;
        $this->anexos = $anexos;
        $this->anexos2 = $anexos2;
    }

    function setAssunto($assunto)
    {
        $this->assunto = $assunto;
    }

    function setEmail($email)
    {
        $this->email = $email;
    }

    function setMensagem($mensagem)
    {
        $this->mensagem = $mensagem;
    }

    function setAnexos($anexos)
    {
        $this->anexos = $anexos;
    }

    /**
     * Diretório de onde anexos podem ser lidos: o mesmo diretório base do OliviaUpload\Upload
     * (OLIVIA_UPLOAD_DIR ou storage/uploads).
     */
    public static function uploadDirectory(): string
    {
        return \OliviaUpload\Upload::baseDirectory();
    }

    /**
     * Resolve um anexo para um caminho dentro do diretório de upload, ou null se inválido.
     */
    public static function resolveAttachment($arquivo): ?string
    {
        $src = is_object($arquivo) ? ($arquivo->src ?? null) : (is_array($arquivo) ? ($arquivo['src'] ?? null) : $arquivo);
        if (!is_string($src) || $src === '') {
            return null;
        }

        $base = realpath(self::uploadDirectory());
        $path = realpath(self::uploadDirectory() . DIRECTORY_SEPARATOR . $src);
        if ($base === false || $path === false || !is_file($path)) {
            return null;
        }

        return strpos($path, $base . DIRECTORY_SEPARATOR) === 0 ? $path : null;
    }

    public function enviar()
    {
        if ($this->email == "") {
            return "Destinatário não informado.";
        }

        if (!class_exists(self::PHPMAILER_CLASS)) {
            return "PHPMailer não está instalado (composer require phpmailer/phpmailer).";
        }

        $host = self::env('OLIVIA_SMTP_HOST');
        $user = self::env('OLIVIA_SMTP_USER');
        if ($host === '' || $user === '') {
            return "Configuração SMTP ausente (OLIVIA_SMTP_HOST / OLIVIA_SMTP_USER).";
        }

        try {
            $class = self::PHPMAILER_CLASS;
            $mailer = new $class(true);
            $mailer->isSMTP();
            $mailer->SMTPDebug = 0;
            $mailer->Host = $host;
            $mailer->Port = (int) (self::env('OLIVIA_SMTP_PORT') ?: 587);
            $mailer->SMTPAuth = true;
            $mailer->Username = $user;
            $mailer->Password = self::env('OLIVIA_SMTP_PASSWORD');
            $secure = self::env('OLIVIA_SMTP_SECURE', 'tls');
            if ($secure !== '') {
                $mailer->SMTPSecure = $secure;
            }
            $mailer->CharSet = "UTF-8";
            $mailer->setFrom(self::env('OLIVIA_SMTP_FROM') ?: $user, self::env('OLIVIA_SMTP_FROM_NAME'));
            $mailer->addAddress($this->email);
            $mailer->Subject = (string) $this->assunto;
            $mailer->isHTML(true);
            $mailer->Body = (string) $this->mensagem;

            foreach ([$this->anexos, $this->anexos2] as $lista) {
                foreach ((array) ($lista ?? []) as $arquivo) {
                    $path = self::resolveAttachment($arquivo);
                    if ($path === null) {
                        return "Anexo inválido ou fora do diretório de upload.";
                    }
                    $mailer->addAttachment($path);
                }
            }

            $mailer->send();
            return "E-mail enviado com sucesso !!!";
        } catch (Exception $erro) {
            error_log('OliviaLib\\Email: ' . $erro->getMessage());
            return "Falha ao enviar o e-mail.";
        }
    }

    private static function env(string $name, string $default = ''): string
    {
        $value = getenv($name);
        if ($value === false) {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }

        return $value === null || $value === false ? $default : (string) $value;
    }
}
