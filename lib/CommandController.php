<?php

namespace OliviaLib;

use OliviaLib\Support\Crypto;
use OliviaLib\Support\Formatos;

/**
 * Classe base de controllers, middlewares e views.
 *
 * Mantém apenas navegação, CSRF, leitura de requisição e mensagens flash.
 * Formatação e criptografia vivem em OliviaLib\Support\Formatos e OliviaLib\Support\Crypto;
 * os métodos antigos continuam aqui como atalhos @deprecated e serão removidos na 3.0.
 */
class CommandController
{
    private const FLASH_KEY = '_olivia_flash';

    // ------------------------------------------------------------------
    // Navegação
    // ------------------------------------------------------------------

    /**
     * URL relativa à aplicação: route('usuarios', ['id' => 1]) => "./usuarios?id=1".
     */
    public function route($view, $parametros = null)
    {
        return './' . ltrim((string) $view, '/\\') . ($parametros != null ? '?' . http_build_query($parametros) : '');
    }

    public function url($view, $parametros = null)
    {
        return $view . ($parametros != null ? '?' . http_build_query($parametros) : '');
    }

    /**
     * Redireciona para uma rota relativa da aplicação e encerra a requisição.
     * Substitui view() e msgbox(): combine com flash() para exibir uma mensagem no destino.
     */
    public function redirect($view, $parametros = null, int $status = 302): never
    {
        header('Location: ' . $this->route($view, $parametros), true, $status);
        exit;
    }

    // ------------------------------------------------------------------
    // Mensagens flash (sobrevivem a um redirect e são lidas uma única vez)
    // ------------------------------------------------------------------

    public function flash(string $mensagem, string $tipo = 'info'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION[self::FLASH_KEY][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }

    /**
     * Retorna e limpa as mensagens flash pendentes. Escape a saída ao renderizar.
     *
     * @return array<int, array{tipo: string, mensagem: string}>
     */
    public function pullFlash(): array
    {
        $mensagens = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);

        return is_array($mensagens) ? $mensagens : [];
    }

    // ------------------------------------------------------------------
    // CSRF
    // ------------------------------------------------------------------

    public function csrf_field()
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars((string) $this->csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function csrf_token()
    {
        return $_SESSION['UUID'] ?? '';
    }

    // ------------------------------------------------------------------
    // Requisição
    // ------------------------------------------------------------------

    public function requestPost($dado)
    {
        return filter_input(INPUT_POST, $dado);
    }

    public function requestGet($dado)
    {
        return filter_input(INPUT_GET, $dado);
    }

    // ------------------------------------------------------------------
    // Atalhos legados (@deprecated — use OliviaLib\Support\*)
    // ------------------------------------------------------------------

    /** @deprecated Use Formatos::letraMaiuscula(). */
    public function letra_maiuscula($string)
    {
        return Formatos::letraMaiuscula($string);
    }

    /** @deprecated Use Formatos::mesPorExtenso(). Agora 1 => "Janeiro" e 12 => "Dezembro". */
    public function mes_do_ano_por_extenso($mes)
    {
        return Formatos::mesPorExtenso($mes);
    }

    /** @deprecated Use Formatos::tirarAcentos(). */
    public function tirarAcentos($string)
    {
        return Formatos::tirarAcentos($string);
    }

    /** @deprecated Use Formatos::mask(). */
    public function mask($mask, $str)
    {
        return Formatos::mask($mask, $str);
    }

    /** @deprecated Use Formatos::validaCpf(). */
    public function validaCPF($cpf)
    {
        return Formatos::validaCpf($cpf);
    }

    /** @deprecated Use Formatos::formatString(). */
    public function format_string($mask, $str, $ch = '#')
    {
        return Formatos::formatString($mask, $str, $ch);
    }

    /** @deprecated Use Formatos::formataData(). */
    public function formataData($data, $d = null, $t = null)
    {
        return Formatos::formataData($data, $d, $t);
    }

    /** @deprecated Use Crypto::encrypt(). $secret_iv é ignorado no formato v2. */
    public function encryptIt($q, $secret_key = null, $secret_iv = null)
    {
        return Crypto::encrypt($q, $secret_key);
    }

    /**
     * @deprecated Use Crypto::decrypt() (somente v2). Valores CBC legados só são lidos com
     * OLIVIA_CRYPTO_LEGACY=true (usando $secret_iv, se informado) ou via Crypto::decryptLegacy().
     */
    public function decryptIt($q, $secret_key = null, $secret_iv = null)
    {
        return Crypto::decryptCompat($q, $secret_key, $secret_iv);
    }
}
