<?php

declare(strict_types=1);

namespace OliviaDownload;

use RuntimeException;

class Download
{
    private const CHUNK_SIZE = 8192; // 8 KB

    /**
     * Força o download de um arquivo.
     */
    public function now(string $file, ?string $fileName = null): never
    {
        $this->send($file, $fileName, disposition: 'attachment');
    }

    /**
     * Exibe o arquivo inline no navegador (PDF, imagem, etc.).
     */
    public function inline(string $file, ?string $fileName = null): never
    {
        $this->send($file, $fileName, disposition: 'inline');
    }

    /**
     * Envia o arquivo com streaming em chunks, suportando Range Requests (resume).
     */
    private function send(string $file, ?string $fileName, string $disposition): never
    {
        $this->assertReadable($file);
        $this->flushOutputBuffers();

        $resolvedName = $this->resolveFileName($file, $fileName);
        $mime         = mime_content_type($file) ?: 'application/octet-stream';
        $fileSize     = filesize($file);

        [$start, $end] = $this->parseRange($fileSize);
        $length = $end - $start + 1;

        if ($start > 0 || $end < $fileSize - 1) {
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
        }

        header("Content-Type: {$mime}");
        header("Content-Disposition: {$disposition}; filename=\"{$resolvedName}\"; filename*=UTF-8''" . rawurlencode($resolvedName));
        header("Content-Length: {$length}");
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $this->stream($file, $start, $end);

        exit;
    }

    /**
     * Lê e envia o arquivo em chunks para não estourar memória em arquivos grandes.
     */
    private function stream(string $file, int $start, int $end): void
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Falha ao abrir arquivo: {$file}");
        }

        try {
            fseek($handle, $start);
            $remaining = $end - $start + 1;

            while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
                $chunk = fread($handle, min(self::CHUNK_SIZE, $remaining));

                if ($chunk === false) {
                    break;
                }

                echo $chunk;
                flush();
                $remaining -= strlen($chunk);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Resolve o nome final do arquivo preservando a extensão original.
     */
    private function resolveFileName(string $file, ?string $fileName): string
    {
        $basename = basename($file);

        if ($fileName === null) {
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

    /**
     * Interpreta o header Range para suportar download resumível.
     *
     * @return array{0: int, 1: int} [start, end]
     */
    private function parseRange(int $fileSize): array
    {
        $range = $_SERVER['HTTP_RANGE'] ?? null;

        if ($range === null || !str_starts_with($range, 'bytes=')) {
            return [0, $fileSize - 1];
        }

        $parts = explode('-', substr($range, 6), 2);
        $start = $parts[0] !== '' ? (int) $parts[0] : 0;
        $end   = ($parts[1] ?? '') !== '' ? (int) $parts[1] : $fileSize - 1;

        // Validação de limites
        $start = max(0, min($start, $fileSize - 1));
        $end   = max($start, min($end, $fileSize - 1));

        return [$start, $end];
    }

    /**
     * Garante que o arquivo existe e é legível.
     */
    private function assertReadable(string $file): void
    {
        if (!is_file($file)) {
            http_response_code(404);
            throw new RuntimeException("Arquivo não encontrado: {$file}");
        }

        if (!is_readable($file)) {
            http_response_code(403);
            throw new RuntimeException("Sem permissão de leitura: {$file}");
        }
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