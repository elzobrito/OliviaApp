<?php

namespace OliviaLib\Support;

/**
 * Criptografia autenticada do framework (OLIVIA-SEC-303, OLIVIA-SEC-313).
 *
 * Formato v2: "v2." + base64url(IV[12] . tag[16] . ciphertext), AES-256-GCM.
 * AAD: "olivia:v2" sem contexto, ou "olivia:v2|ctx:<contexto>" — um valor cifrado num contexto
 * não decifra em outro nem sem contexto (valores v2 já emitidos sem contexto continuam válidos).
 * Chave: parâmetro explícito (string com >= 16 caracteres — em caracteres se UTF-8, em bytes se
 * binária; inválida falha fechado, sem cair no ambiente) -> OLIVIA_APP_KEY -> secret_key da sessão.
 * A mesma política vale para a leitura legada por opt-in; decryptLegacy() aceita chaves curtas antigas.
 * decrypt() aceita somente v2. Valores legados AES-256-CBC (sem autenticação) só são lidos por
 * decryptLegacy() ou, para migração, por decrypt() quando OLIVIA_CRYPTO_LEGACY=true.
 */
final class Crypto
{
    private const CIPHER_V2 = 'aes-256-gcm';
    private const PREFIX_V2 = 'v2.';
    private const AAD_V2 = 'olivia:v2';
    private const MIN_EXPLICIT_KEY = 16;

    private static bool $sessionKeyWarned = false;

    /**
     * Resolve a chave binária de 32 bytes para o formato v2 (AES-256-GCM).
     *
     * Ordem: chave explícita do chamador -> OLIVIA_APP_KEY -> secret_key da sessão (com aviso).
     * OLIVIA_APP_KEY aceita "base64:<32 bytes em base64>" ou texto com pelo menos 32 caracteres.
     * Retorna null quando não há chave válida (falha fechada).
     */
    private static function resolveKey($secret_key = null): ?string
    {
        if ($secret_key !== null) {
            // Chave explícita inválida nunca cai no ambiente/sessão: o chamador pediu esta chave.
            if (!self::explicitKeyAccepted($secret_key)) {
                return null;
            }
            return hash('sha256', $secret_key, true);
        }

        $appKey = getenv('OLIVIA_APP_KEY');
        if ($appKey === false) {
            $appKey = $_ENV['OLIVIA_APP_KEY'] ?? $_SERVER['OLIVIA_APP_KEY'] ?? '';
        }
        $appKey = (string) $appKey;

        if ($appKey !== '') {
            if (strpos($appKey, 'base64:') === 0) {
                $decoded = base64_decode(substr($appKey, 7), true);
                if ($decoded === false || strlen($decoded) !== 32) {
                    error_log('OliviaLib: OLIVIA_APP_KEY base64 deve conter exatamente 32 bytes.');
                    return null;
                }
                return $decoded;
            }
            if (strlen($appKey) < 32) {
                error_log('OliviaLib: OLIVIA_APP_KEY deve ter pelo menos 32 caracteres.');
                return null;
            }
            return hash('sha256', $appKey, true);
        }

        $sessionKey = $_SESSION['secret_key'] ?? null;
        if (!is_string($sessionKey) || $sessionKey === '') {
            return null;
        }
        if (!self::$sessionKeyWarned) {
            self::$sessionKeyWarned = true;
            error_log('OliviaLib: OLIVIA_APP_KEY ausente; usando chave da sessão (valores cifrados expiram com a sessão).');
        }
        return hash('sha256', $sessionKey, true);
    }

    /**
     * Política da chave explícita nas APIs gerais (encrypt, decrypt e leitura legada por opt-in):
     * null (sem chave explícita) ou string com pelo menos 16 caracteres. O comprimento é contado em
     * caracteres quando a chave é UTF-8 válida e em bytes quando é binária. Inválida => log e false.
     */
    private static function explicitKeyAccepted($secret_key): bool
    {
        if ($secret_key === null) {
            return true;
        }
        if (is_string($secret_key)) {
            $tamanho = preg_match('//u', $secret_key) === 1
                ? (function_exists('mb_strlen') ? mb_strlen($secret_key, 'UTF-8') : (int) preg_match_all('/./su', $secret_key))
                : strlen($secret_key);
            if ($tamanho >= self::MIN_EXPLICIT_KEY) {
                return true;
            }
        }
        error_log('OliviaLib: chave explícita do Crypto deve ser string com pelo menos ' . self::MIN_EXPLICIT_KEY . ' caracteres.');

        return false;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value)
    {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $value) !== 1) {
            return false;
        }
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        return base64_decode($padded, true);
    }

    /**
     * Normaliza a entrada (string, número, primeiro item de array ou null). Retorna null se inválida.
     */
    private static function normalizeInput($string): ?string
    {
        // Garante que $string seja string (ou vazio)
        if (is_array($string)) {
            // Se for array, pega o primeiro valor (comportamento comum em cookies/session)
            $string = reset($string);
        }

        if (is_int($string) || is_float($string)) {
            $string = (string) $string;
        }

        if (!is_string($string) && $string !== null) {
            return null;
        }

        return $string ?? '';
    }

    /**
     * AAD do formato v2. Contexto null ou '' mantém o AAD original; qualquer outro valor é vinculado.
     * Contexto não-string é inválido (null retornado => falha fechada).
     */
    private static function aad($context): ?string
    {
        if ($context === null || $context === '') {
            return self::AAD_V2;
        }
        if (!is_string($context)) {
            return null;
        }

        return self::AAD_V2 . '|ctx:' . $context;
    }

    /**
     * OLIVIA_CRYPTO_LEGACY=true habilita a leitura CBC em decrypt() durante a migração.
     * Valor inválido é ignorado (registrado em log) e a leitura legada permanece desligada.
     */
    public static function legacyEnabled(): bool
    {
        $valor = getenv('OLIVIA_CRYPTO_LEGACY');
        if ($valor === false || trim($valor) === '') {
            return false;
        }
        $ligado = filter_var(trim($valor), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($ligado === null) {
            error_log('OliviaLib: OLIVIA_CRYPTO_LEGACY inválido; use true ou false. Leitura legada desligada.');
            return false;
        }

        return $ligado;
    }

    /**
     * Formato v2: "v2." + base64url(iv[12] . tag[16] . ciphertext), AES-256-GCM.
     */
    private static function encryptV2(string $plain, $secret_key, string $aad)
    {
        $key = self::resolveKey($secret_key);
        if ($key === null) {
            return false;
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER_V2, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if ($cipher === false || strlen($tag) !== 16) {
            return false;
        }

        return self::PREFIX_V2 . self::base64UrlEncode($iv . $tag . $cipher);
    }

    private static function decryptV2(string $payload, $secret_key, string $aad)
    {
        $key = self::resolveKey($secret_key);
        if ($key === null) {
            return false;
        }

        $raw = self::base64UrlDecode($payload);
        if ($raw === false || strlen($raw) < 28) {
            return false;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        return openssl_decrypt($cipher, self::CIPHER_V2, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
    }

    /**
     * Leitura do formato legado (AES-256-CBC, IV fixo, sem autenticação).
     * Somente decifra — novos valores são sempre gerados em v2.
     *
     * @deprecated Mantido apenas para compatibilidade com valores emitidos antes da v2.
     */
    private static function decryptLegacyCbc(string $string, $secret_key = null, $secret_iv = null)
    {
        $secret_key = $secret_key ?? ($_SESSION['secret_key'] ?? null);
        $secret_iv  = $secret_iv  ?? ($_SESSION['secret_iv'] ?? null);

        // Leitura de dados existentes: sem tamanho mínimo, mas chave/IV precisam ser strings não vazias.
        if (!is_string($secret_key) || !is_string($secret_iv) || $secret_key === '' || $secret_iv === '') {
            return false;
        }

        $decoded = base64_decode($string, true);
        if ($decoded === false) {
            return false;
        }

        $key = hash('sha256', $secret_key);
        $iv  = substr(hash('sha256', $secret_iv), 0, 16);

        return openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Cifra $value (string ou número) em v2. $context (opcional) é vinculado ao AAD: o valor só
     * decifra com o mesmo contexto. Retorna false sem chave válida ou com contexto não-string.
     */
    public static function encrypt($value, $secretKey = null, $context = null)
    {
        $plain = self::normalizeInput($value);
        $aad = self::aad($context);
        if ($plain === null || $aad === null) {
            return false;
        }

        return self::encryptV2($plain, $secretKey, $aad);
    }

    /**
     * Decifra somente valores v2 (com o mesmo $context usado no encrypt). Retorna false se inválido,
     * adulterado, de outro contexto ou legado. Com OLIVIA_CRYPTO_LEGACY=true, valores sem prefixo v2
     * são lidos como CBC legado (IV da sessão); prefira decryptLegacy() explícito na migração.
     */
    public static function decrypt($value, $secretKey = null, $context = null)
    {
        return self::decryptGeneral($value, $secretKey, $context, null);
    }

    /**
     * Compatibilidade do atalho CommandController::decryptIt($q, $chave, $iv): igual a decrypt() sem
     * contexto, mas a leitura legada (OLIVIA_CRYPTO_LEGACY=true) usa o $secretIv informado.
     * A entrada é normalizada antes da classificação (array => primeiro item).
     *
     * @internal
     */
    public static function decryptCompat($value, $secretKey = null, $secretIv = null)
    {
        return self::decryptGeneral($value, $secretKey, null, $secretIv);
    }

    private static function decryptGeneral($value, $secretKey, $context, $secretIv)
    {
        $string = self::normalizeInput($value);
        $aad = self::aad($context);
        if ($string === null || $aad === null) {
            return false;
        }
        if (strpos($string, self::PREFIX_V2) === 0) {
            return self::decryptV2(substr($string, strlen(self::PREFIX_V2)), $secretKey, $aad);
        }
        // Leitura legada por opt-in segue a mesma política de chave explícita das APIs gerais;
        // chaves curtas antigas só pelo caminho explícito de migração decryptLegacy().
        if ($context === null && self::legacyEnabled() && self::explicitKeyAccepted($secretKey)) {
            return self::decryptLegacyCbc($string, $secretKey, $secretIv);
        }

        return false;
    }

    /**
     * Leitura explícita do formato legado AES-256-CBC (sem autenticação; IV fixo derivado de $secretIv
     * ou do secret_iv da sessão). Somente CBC: valores v2 retornam false. Use para migrar dados:
     * leia com decryptLegacy() e regrave com encrypt().
     *
     * @deprecated Apenas para migração de valores emitidos antes da v2.
     */
    public static function decryptLegacy($value, $secretKey = null, $secretIv = null)
    {
        $string = self::normalizeInput($value);
        if ($string === null || strpos($string, self::PREFIX_V2) === 0) {
            return false;
        }

        return self::decryptLegacyCbc($string, $secretKey, $secretIv);
    }
}
