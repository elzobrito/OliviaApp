<?php

namespace OliviaLib\Support;

/**
 * Utilitários de formatação e validação (pt-BR), sem estado e sem herança.
 *
 * Substituem os helpers homônimos de OliviaLib\CommandController (OLIVIA-CLEAN-307),
 * com correção dos bugs de índice, máscara e data.
 */
final class Formatos
{
    private const MESES = [
        1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    /**
     * Nome do mês por extenso: 1 => "Janeiro" ... 12 => "Dezembro"; fora do intervalo => null.
     */
    public static function mesPorExtenso($mes): ?string
    {
        return self::MESES[(int) $mes] ?? null;
    }

    /**
     * Capitaliza palavras com mais de 3 letras e deixa as demais em minúsculas (multibyte).
     * Ex.: "MARIA DA SILVA" => "Maria da Silva".
     */
    public static function letraMaiuscula($texto): string
    {
        $palavras = explode(' ', (string) $texto);
        foreach ($palavras as $i => $palavra) {
            $palavra = mb_strtolower($palavra, 'UTF-8');
            if (mb_strlen($palavra, 'UTF-8') > 3) {
                $palavra = mb_strtoupper(mb_substr($palavra, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($palavra, 1, null, 'UTF-8');
            }
            $palavras[$i] = $palavra;
        }

        return implode(' ', $palavras);
    }

    public static function tirarAcentos($texto): string
    {
        return (string) preg_replace(
            ['/(á|à|ã|â|ä)/u', '/(Á|À|Ã|Â|Ä)/u', '/(é|è|ê|ë)/u', '/(É|È|Ê|Ë)/u', '/(í|ì|î|ï)/u', '/(Í|Ì|Î|Ï)/u', '/(ó|ò|õ|ô|ö)/u', '/(Ó|Ò|Õ|Ô|Ö)/u', '/(ú|ù|û|ü)/u', '/(Ú|Ù|Û|Ü)/u', '/(ñ)/u', '/(Ñ)/u', '/(ç)/u', '/(Ç)/u'],
            ['a', 'A', 'e', 'E', 'i', 'I', 'o', 'O', 'u', 'U', 'n', 'N', 'c', 'C'],
            (string) $texto
        );
    }

    /**
     * Aplica uma máscara usando somente os dígitos de $valor.
     * Dígitos excedentes são ignorados; com dígitos faltando, a saída termina no último dígito preenchido.
     * Ex.: mask('###.###.###-##', '12345678909') => "123.456.789-09"; mask('##.##', '123456') => "12.34".
     */
    public static function mask($mascara, $valor): string
    {
        return self::preencher((string) $mascara, (string) preg_replace('/\D/', '', (string) $valor), '#');
    }

    /**
     * Como mask(), mas usa todos os caracteres de $valor e um marcador configurável.
     */
    public static function formatString($mascara, $valor, $marcador = '#'): string
    {
        return self::preencher((string) $mascara, (string) $valor, (string) $marcador);
    }

    public static function validaCpf($cpf): bool
    {
        $cpf = (string) preg_replace('/\D/', '', (string) $cpf);

        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Converte "AAAA-MM-DD[ HH:MM:SS]" para "DD/MM/AAAA " e/ou "HH:MM " (mesmo formato do helper legado,
     * incluindo o espaço final). Sem hora na entrada, a parte de hora é omitida. Entrada vazia => null.
     */
    public static function formataData($data, $comData = null, $comHora = null): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }

        $partes = explode(' ', trim((string) $data), 2);
        $retorno = '';

        if ($comData != null) {
            $retorno .= implode('/', array_reverse(explode('-', $partes[0]))) . ' ';
        }

        if ($comHora != null && isset($partes[1]) && $partes[1] !== '') {
            $retorno .= substr($partes[1], 0, 5) . ' ';
        }

        return $retorno;
    }

    private static function preencher(string $mascara, string $valor, string $marcador): string
    {
        if ($marcador === '') {
            return $mascara;
        }

        $saida = '';
        $usados = 0;
        $total = strlen($valor);
        $pendente = '';
        $tamanho = strlen($mascara);

        for ($i = 0; $i < $tamanho; $i++) {
            if ($mascara[$i] === $marcador) {
                if ($usados >= $total) {
                    break;
                }
                $saida .= $pendente . $valor[$usados++];
                $pendente = '';
            } else {
                $pendente .= $mascara[$i];
            }
        }

        // Separadores finais só entram quando a máscara foi totalmente preenchida.
        return $usados > 0 && $i === $tamanho ? $saida . $pendente : $saida;
    }
}
