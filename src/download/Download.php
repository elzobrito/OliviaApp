<?php

declare(strict_types=1);

namespace OliviaDownload;

use InvalidArgumentException;
use OliviaUpload\Upload;
use RuntimeException;

/**
 * Envio de arquivos com streaming e HTTP Range (OLIVIA-SEC-321).
 *
 * - Só serve arquivos dentro do diretório base (padrão: OLIVIA_UPLOAD_DIR / storage/uploads,
 *   ou o informado no construtor). O caminho é resolvido com realpath: "..", caminho absoluto
 *   fora da base e link simbólico que aponta para fora são recusados (404, falha fechada).
 * - Content-Disposition com filename ASCII saneado e filename* (RFC 5987/6266).
 * - Range (RFC 9110): só em GET; "bytes=S-E", "bytes=S-" e sufixo "bytes=-N"; range insatisfazível
 *   responde 416 com "Content-Range: bytes * /tamanho"; múltiplos ranges, sintaxe inválida, HEAD ou
 *   If-Range que não é o ETag forte atual: arquivo inteiro (200). HEAD não envia corpo.
 * - X-Content-Type-Options: nosniff também nas respostas de erro.
 * - Mensagens de exceção nunca incluem o caminho do arquivo.
 */
class Download
{
    private const CHUNK_SIZE = 8192; // 8 KB

    private string $baseDirectory;

    /**
     * @param string|null $baseDirectory diretório raiz permitido; null usa Upload::baseDirectory()
     */
    public function __construct(?string $baseDirectory = null)
    {
        $this->baseDirectory = $baseDirectory ?? Upload::baseDirectory();
    }

    /**
     * Força o download de um arquivo (caminho relativo à base, ou absoluto dentro dela).
     */
    public function now(string $file, ?string $fileName = null): never
    {
        $this->send($file, $fileName, 'attachment');
    }

    /**
     * Exibe o arquivo inline no navegador (PDF, imagem, etc.).
     */
    public function inline(string $file, ?string $fileName = null): never
    {
        $this->send($file, $fileName, 'inline');
    }

    /**
     * Resolve $file dentro da base; devolve o caminho real ou lança RuntimeException (status 404/403 definido).
     */
    public function resolve(string $file): string
    {
        $base = $this->baseDirectory !== '' && !str_contains($this->baseDirectory, "\0") ? realpath($this->baseDirectory) : false;
        if ($base === false || !is_dir($base)) {
            $this->fail(404, 'Diretório base de download não existe.');
        }

        if ($file === '' || str_contains($file, "\0")) {
            $this->fail(404, 'Arquivo não encontrado.');
        }

        $candidate = $this->isAbsolute($file) ? $file : $base . DIRECTORY_SEPARATOR . $file;
        $real = realpath($candidate);
        // realpath não devolve separador final (exceto na raiz); "\\" é caractere válido de nome no Linux.
        $prefix = str_ends_with($base, DIRECTORY_SEPARATOR) ? $base : $base . DIRECTORY_SEPARATOR;

        if ($real === false || !str_starts_with($real, $prefix)) {
            $this->fail(404, $real === false ? 'Arquivo não encontrado.' : 'Arquivo fora do diretório base de download.');
        }

        if (!is_file($real)) {
            $this->fail(404, 'Arquivo não encontrado.');
        }

        if (!is_readable($real)) {
            $this->fail(403, 'Sem permissão de leitura do arquivo.');
        }

        return $real;
    }

    /**
     * Valor do header Content-Disposition: filename ASCII saneado + filename* UTF-8 (RFC 5987).
     */
    public static function contentDisposition(string $disposition, string $fileName): string
    {
        if ($disposition !== 'attachment' && $disposition !== 'inline') {
            throw new InvalidArgumentException('Disposition must be "attachment" or "inline".');
        }

        // Sem componentes de caminho nem caracteres de controle (CR/LF incluídos).
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $fileName);
        $name = trim(str_replace(['/', '\\'], '_', $name), ' .');
        if (preg_match('//u', $name) !== 1) {
            // UTF-8 inválido: troca os bytes fora do ASCII imprimível.
            $name = (string) preg_replace('/[^\x20-\x7E]/', '_', $name);
        }
        if ($name === '') {
            $name = 'download';
        }

        $ascii = (string) preg_replace('/[^\x20-\x7E]|["\\\\;]/u', '_', $name);
        $ascii = $ascii === '' ? 'download' : $ascii;

        return "{$disposition}; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name);
    }

    /**
     * Interpreta um header Range (RFC 9110) para um arquivo de $fileSize bytes.
     *
     * @return array{0: int, 1: int}|false|null [start, end] para 206; false para 416; null para ignorar (200)
     */
    public static function parseRange(?string $range, int $fileSize): array|false|null
    {
        if ($range === null) {
            return null;
        }

        $range = trim($range, " \t");
        // RFC 9110 14.1.1: "bytes=" first-pos "-" [ last-pos ] | "-" suffix-length, sem espaços internos.
        if (preg_match('/^bytes=([0-9]*)-([0-9]*)$/iD', $range, $m) !== 1 || ($m[1] === '' && $m[2] === '')) {
            // Sem "bytes=", sintaxe inválida ou múltiplos ranges (com vírgula): ignora e envia tudo.
            return null;
        }

        if ($m[1] === '') {
            // Sufixo: os últimos N bytes.
            $suffix = self::toInt($m[2]);
            if ($suffix === 0 || $fileSize === 0) {
                return false;
            }

            return [max(0, $fileSize - $suffix), $fileSize - 1];
        }

        // Range invertido é inválido (200), comparado nos dígitos antes de saturar números enormes.
        if ($m[2] !== '' && self::compareDigits($m[2], $m[1]) < 0) {
            return null;
        }

        $start = self::toInt($m[1]);
        $end = $m[2] === '' ? null : self::toInt($m[2]);

        if ($start >= $fileSize) {
            return false;
        }

        return [$start, $end === null ? $fileSize - 1 : min($end, $fileSize - 1)];
    }

    /**
     * Range só vale em GET e, com If-Range, só quando o validador é o ETag forte atual (RFC 9110 13.1.5/14.2).
     * If-Range com data ou ETag fraco/diferente: o Range é ignorado e o arquivo vai inteiro.
     */
    public static function rangeApplies(string $method, ?string $ifRange, string $etag): bool
    {
        if (strtoupper($method) !== 'GET') {
            return false;
        }

        return $ifRange === null || trim($ifRange) === $etag;
    }

    /**
     * Envia o arquivo com streaming em chunks, suportando Range Requests (resume).
     */
    private function send(string $file, ?string $fileName, string $disposition): never
    {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
        }
        $real = $this->resolve($file);
        $this->flushOutputBuffers();

        $handle = @fopen($real, 'rb');
        if ($handle === false) {
            $this->fail(404, 'Falha ao abrir o arquivo para download.');
        }

        try {
            $this->sendOpenedFile($handle, $this->resolveFileName($real, $fileName), $disposition);
        } finally {
            fclose($handle);
        }

        exit;
    }

    /** @param resource $handle */
    private function sendOpenedFile($handle, string $fileName, string $disposition): void
    {
        $stat = fstat($handle);
        if ($stat === false) {
            $this->fail(500, 'Falha ao ler os metadados do arquivo para download.');
        }

        $fileSize = $stat['size'];
        $hash = hash_init('sha256');
        if (hash_update_stream($hash, $handle, $fileSize) !== $fileSize || !rewind($handle)) {
            $this->fail(500, 'Falha ao ler o arquivo para download.');
        }

        $etag = '"' . hash_final($hash) . '"';
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

        header('Accept-Ranges: bytes');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $stat['mtime']) . ' GMT');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $rangeHeader = isset($_SERVER['HTTP_RANGE']) ? (string) $_SERVER['HTTP_RANGE'] : null;
        $ifRange = isset($_SERVER['HTTP_IF_RANGE']) ? (string) $_SERVER['HTTP_IF_RANGE'] : null;
        $range = self::rangeApplies($method, $ifRange, $etag) ? self::parseRange($rangeHeader, $fileSize) : null;

        if ($range === false) {
            http_response_code(416);
            header("Content-Range: bytes */{$fileSize}");
            header('Content-Length: 0');
            return;
        }

        [$start, $end] = $range ?? [0, $fileSize - 1];
        $length = $fileSize === 0 ? 0 : $end - $start + 1;

        if ($range !== null) {
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
        }

        $mime = mime_content_type($handle) ?: 'application/octet-stream';
        header("Content-Type: {$mime}");
        header('Content-Disposition: ' . self::contentDisposition($disposition, $fileName));
        header("Content-Length: {$length}");

        if ($length > 0 && strtoupper($method) !== 'HEAD') {
            $this->stream($handle, $start, $end);
        }
    }

    /**
     * Lê e envia o arquivo em chunks para não estourar memória em arquivos grandes.
     *
     * @param resource $handle
     */
    private function stream($handle, int $start, int $end): void
    {
        if (fseek($handle, $start) !== 0) {
            $this->fail(500, 'Falha ao posicionar a leitura do arquivo para download.');
        }

        $remaining = $end - $start + 1;

        while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
            $chunk = fread($handle, min(self::CHUNK_SIZE, $remaining));

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;
            flush();
            $remaining -= strlen($chunk);
        }
    }

    /**
     * Resolve o nome final do arquivo preservando a extensão original.
     */
    private function resolveFileName(string $file, ?string $fileName): string
    {
        $basename = basename($file);

        if ($fileName === null || $fileName === '') {
            return $basename;
        }

        // Se o nome personalizado já tem extensão, usa como está
        if (pathinfo($fileName, PATHINFO_EXTENSION) !== '') {
            return $fileName;
        }

        // Caso contrário, anexa a extensão do arquivo original
        $ext = pathinfo($basename, PATHINFO_EXTENSION);

        return $ext !== '' ? "{$fileName}.{$ext}" : $fileName;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private static function compareDigits(string $a, string $b): int
    {
        $a = ltrim($a, '0');
        $b = ltrim($b, '0');

        return strlen($a) <=> strlen($b) ?: strcmp($a, $b) <=> 0;
    }

    private static function toInt(string $digits): int
    {
        $digits = ltrim($digits, '0');

        return $digits === '' ? 0 : (strlen($digits) > 18 ? PHP_INT_MAX : (int) $digits);
    }

    private function fail(int $status, string $message): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('X-Content-Type-Options: nosniff');
        }

        throw new RuntimeException($message);
    }

    /**
     * Limpa todos os buffers de saída pendentes.
     */
    private function flushOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
