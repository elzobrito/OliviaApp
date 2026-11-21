<?php

namespace OliviaUpload;

use Ramsey\Uuid\Uuid;

/**
 * Upload de arquivo endurecido (OLIVIA-SEC-304).
 *
 * - Destino: OLIVIA_UPLOAD_DIR (ou <raiz do projeto>/storage/uploads, fora do webroot) + subpasta $pasta;
 *   nunca depende de REQUEST_URI e sempre resolve dentro do diretório base.
 * - Validação: código de erro do PHP, tamanho (MB), extensão na allowlist do chamador, bloqueio de
 *   extensões executáveis e MIME real (finfo) compatível com a extensão.
 * - O arquivo é sempre gravado com nome UUID + extensão (getArqNameCripto()); o nome original fica
 *   disponível apenas como metadado (getArqName()).
 * - Permissões: diretórios 0750 e arquivos 0640, aplicadas explicitamente (independem do umask);
 *   falha ao aplicá-las é reportada e o upload é desfeito.
 * - Contenção (OLIVIA-SEC-312): cada ancestral da pasta de destino é resolvido e conferido dentro do
 *   diretório base ANTES de qualquer mkdir; links simbólicos para fora da base são recusados.
 * - Office OpenXML (docx/xlsx/pptx) exige [Content_Types].xml e a parte principal; ODF (odt/ods) exige
 *   o arquivo "mimetype" com o tipo correto. ZIP genérico com extensão de escritório é recusado.
 * - Extensões sem MIME conhecido só são aceitas com MIME declarado pelo chamador:
 *   new Upload('docs', 5, ['pdf', 'dwg' => 'image/vnd.dwg'], $_FILES['f']) (também aceita lista de MIMEs).
 *
 * A assinatura do construtor é a mesma da versão anterior; $renomear e $permissao são aceitos
 * por compatibilidade, mas o arquivo é sempre renomeado e as permissões são fixas.
 */
class Upload
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'jspx', 'exe', 'dll', 'bat', 'cmd',
        'com', 'msi', 'htaccess', 'htpasswd', 'html', 'htm', 'xhtml', 'shtml', 'js', 'mjs', 'svg', 'svgz',
    ];

    private const BLOCKED_MIME_PATTERNS = [
        '#php#i', '#x-httpd#i', '#x-sh#i', '#x-shellscript#i', '#x-executable#i', '#x-msdownload#i',
        '#x-dosexec#i', '#text/html#i', '#javascript#i', '#svg#i', '#x-perl#i', '#x-python#i',
    ];

    private const MIME_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'json' => ['application/json', 'text/plain'],
        'xml' => ['application/xml', 'text/xml'],
        'zip' => ['application/zip'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'mp3' => ['audio/mpeg'],
        'mp4' => ['video/mp4'],
    ];

    /** Parte principal exigida em cada pacote Office OpenXML, além de [Content_Types].xml. */
    private const OOXML_MAIN_PART = [
        'docx' => 'word/document.xml',
        'xlsx' => 'xl/workbook.xml',
        'pptx' => 'ppt/presentation.xml',
    ];

    /** Conteúdo exato do arquivo "mimetype" de cada pacote ODF. */
    private const ODF_MIMETYPE = [
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
    ];

    private $pasta;
    private $tamanho;
    private $extensoes;
    /** @var array<string, string[]> MIMEs declarados pelo chamador, por extensão */
    private $mimesDeclarados = [];
    private $arquivo;
    // O nome original do arquivo no computador do usuário (somente metadado)
    private $arqName = '';
    // Nome gravado em disco (UUID + extensão)
    private $arqNameCripto = '';
    // Extensão normalizada do nome original
    private $arqType = '';
    private $arqMime = '';
    private $arqSize = 0;
    private $arqTemp = '';
    // 0 = sucesso; 1 = falha (detalhes em getError())
    private $arqError = 0;
    private $msgError = array();
    private $destino = '';

    public static function baseDirectory(): string
    {
        $configured = getenv('OLIVIA_UPLOAD_DIR');
        if ($configured === false || $configured === '') {
            $configured = $_ENV['OLIVIA_UPLOAD_DIR'] ?? $_SERVER['OLIVIA_UPLOAD_DIR'] ?? '';
        }

        $base = $configured !== '' ? (string) $configured : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';

        return rtrim($base, '/\\');
    }

    /**
     * @param string     $pasta     subpasta relativa ao diretório base ('' para a raiz)
     * @param int|float  $tamanho   limite em MB
     * @param string[]   $extensoes allowlist de extensões (ex.: ['pdf', 'jpg'])
     * @param array      $arquivo   item de $_FILES
     * @param bool       $renomear  ignorado (sempre renomeia) — compatibilidade
     * @param mixed      $permissao ignorado (permissões fixas) — compatibilidade
     */
    function __construct($pasta, $tamanho, $extensoes, $arquivo, $renomear = true, $permissao = null)
    {
        $this->tamanho = (float) $tamanho;
        $this->extensoes = [];
        foreach ((array) $extensoes as $chave => $valor) {
            if (is_int($chave)) {
                $this->extensoes[] = strtolower(ltrim((string) $valor, '.'));
                continue;
            }
            // 'ext' => 'mime/tipo' ou 'ext' => ['mime/a', 'mime/b']
            $ext = strtolower(ltrim((string) $chave, '.'));
            $this->extensoes[] = $ext;
            $this->mimesDeclarados[$ext] = array_values(array_filter(array_map(
                fn ($m) => strtolower(trim((string) $m)),
                (array) $valor
            ), fn ($m) => $m !== ''));
        }
        $this->arquivo = is_array($arquivo) ? $arquivo : [];

        $this->arqName = basename((string) ($this->arquivo['name'] ?? ''));
        $this->arqSize = (int) ($this->arquivo['size'] ?? 0);
        $this->arqTemp = (string) ($this->arquivo['tmp_name'] ?? '');
        $this->arqType = strtolower((string) pathinfo($this->arqName, PATHINFO_EXTENSION));

        $code = $this->arquivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ((int) $code !== UPLOAD_ERR_OK) {
            $this->fail($this->uploadErrorMessage((int) $code));
            return;
        }

        $pastaResolvida = $this->resolverPasta((string) $pasta);
        if ($pastaResolvida === null) {
            return;
        }
        $this->pasta = $pastaResolvida;

        $this->executa();
    }

    public function getError()
    {
        return $this->msgError;
    }

    /**
     * Código de erro: 0 = sucesso, 1 = falha.
     */
    function getErro()
    {
        return $this->arqError;
    }

    public function isValid(): bool
    {
        return $this->arqError === 0 && $this->destino !== '';
    }

    function getArqNameCripto()
    {
        return $this->arqNameCripto;
    }

    function getArqName()
    {
        return $this->arqName;
    }

    public function getMime(): string
    {
        return $this->arqMime;
    }

    /**
     * Caminho absoluto do arquivo gravado ('' em caso de falha).
     */
    public function getPath(): string
    {
        return $this->destino;
    }

    public function delete_file()
    {
        if ($this->destino !== '' && is_file($this->destino) && $this->dentroDoBase($this->destino)) {
            unlink($this->destino);
        }
    }

    /**
     * Move o arquivo temporário. Isolado para permitir testes fora de uma requisição HTTP.
     */
    protected function moverArquivo(string $origem, string $destino): bool
    {
        return is_uploaded_file($origem) && move_uploaded_file($origem, $destino);
    }

    /**
     * Aplica permissões. Isolado para permitir testar a falha (ex.: sistema de arquivos sem suporte).
     */
    protected function aplicarPermissao(string $caminho, int $modo): bool
    {
        return @chmod($caminho, $modo);
    }

    private function executa(): void
    {
        if (!$this->valida_extensao() || !$this->valida_tamanho() || !$this->valida_mime()) {
            return;
        }

        $this->arqNameCripto = Uuid::uuid4()->toString() . '.' . $this->arqType;
        $destino = $this->pasta . DIRECTORY_SEPARATOR . $this->arqNameCripto;

        if (!$this->moverArquivo($this->arqTemp, $destino)) {
            $this->arqNameCripto = '';
            $this->fail('Não foi possível gravar o arquivo enviado.');
            return;
        }

        if (!$this->aplicarPermissao($destino, 0640)) {
            @unlink($destino);
            $this->arqNameCripto = '';
            $this->fail('Não foi possível aplicar as permissões do arquivo enviado.');
            return;
        }
        $this->destino = $destino;
    }

    private function valida_extensao(): bool
    {
        if ($this->arqType === '' || in_array($this->arqType, self::BLOCKED_EXTENSIONS, true)) {
            return $this->fail('O tipo de arquivo enviado é inválido!');
        }

        // Extensões intermediárias executáveis (ex.: "foto.php.jpg") também são recusadas.
        $partes = explode('.', strtolower($this->arqName));
        array_shift($partes);
        array_pop($partes);
        foreach ($partes as $parte) {
            if (in_array($parte, self::BLOCKED_EXTENSIONS, true)) {
                return $this->fail('O tipo de arquivo enviado é inválido!');
            }
        }

        if (!in_array($this->arqType, $this->extensoes, true)) {
            return $this->fail('O tipo de arquivo enviado é inválido!');
        }

        return true;
    }

    private function valida_tamanho(): bool
    {
        $real = is_file($this->arqTemp) ? (int) filesize($this->arqTemp) : $this->arqSize;
        $limite = (int) (1024 * 1024 * $this->tamanho);
        if ($limite <= 0 || $real > $limite || $this->arqSize > $limite) {
            return $this->fail('O tamanho do arquivo enviado é maior que o limite!');
        }
        if ($real === 0) {
            return $this->fail('O arquivo enviado está vazio.');
        }
        return true;
    }

    private function valida_mime(): bool
    {
        if (!is_file($this->arqTemp) || !class_exists(\finfo::class)) {
            return $this->fail('Não foi possível verificar o tipo do arquivo.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($this->arqTemp);
        $this->arqMime = $mime;

        foreach (self::BLOCKED_MIME_PATTERNS as $pattern) {
            if (preg_match($pattern, $mime) === 1) {
                return $this->fail('O conteúdo do arquivo não é permitido.');
            }
        }

        // Conteúdo PHP disfarçado (ex.: polyglot imagem + "<?php").
        $inicio = (string) @file_get_contents($this->arqTemp, false, null, 0, 1024 * 1024);
        if (stripos($inicio, '<?php') !== false) {
            return $this->fail('O conteúdo do arquivo não é permitido.');
        }

        // MIME declarado pelo chamador tem precedência; sem declaração, vale o mapa interno; extensão
        // sem nenhum dos dois é recusada (não há como saber se o conteúdo corresponde à extensão).
        $esperados = $this->mimesDeclarados[$this->arqType] ?? self::MIME_BY_EXTENSION[$this->arqType] ?? null;
        if ($esperados === null || $esperados === []) {
            return $this->fail('Extensão sem tipo MIME conhecido: declare o MIME aceito (ex.: [\'' . $this->arqType . '\' => \'tipo/subtipo\']).');
        }
        if (!in_array(strtolower($mime), array_map('strtolower', $esperados), true)) {
            return $this->fail('O conteúdo do arquivo não corresponde à extensão.');
        }

        return $this->valida_pacote();
    }

    /**
     * Pacotes ZIP com extensão de escritório precisam ter a estrutura do formato declarado.
     */
    private function valida_pacote(): bool
    {
        $ooxml = self::OOXML_MAIN_PART[$this->arqType] ?? null;
        $odf = self::ODF_MIMETYPE[$this->arqType] ?? null;
        if ($ooxml === null && $odf === null) {
            return true;
        }
        if (!class_exists(\ZipArchive::class)) {
            return $this->fail('Não foi possível verificar a estrutura do documento (extensão zip ausente).');
        }

        $zip = new \ZipArchive();
        if ($zip->open($this->arqTemp, \ZipArchive::RDONLY) !== true) {
            return $this->fail('O documento enviado não é um pacote válido.');
        }
        try {
            if ($ooxml !== null) {
                $valido = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($ooxml) !== false;
            } else {
                // Leitura limitada: o tamanho do upload mede o pacote comprimido, não a entrada descomprimida.
                $info = $zip->statName('mimetype');
                $valido = is_array($info) && (int) ($info['size'] ?? -1) === strlen($odf)
                    && $zip->getFromName('mimetype', strlen($odf)) === $odf;
            }
        } finally {
            $zip->close();
        }

        return $valido ? true : $this->fail('O documento enviado não tem a estrutura do formato declarado.');
    }

    private function resolverPasta(string $pasta): ?string
    {
        $pasta = trim(str_replace('\\', '/', $pasta), '/');
        if ($pasta !== '' && (preg_match('#(^|/)\.\.?(/|$)#', $pasta) === 1 || preg_match('#[^A-Za-z0-9_\-/]#', $pasta) === 1)) {
            $this->fail('Pasta de destino inválida.');
            return null;
        }

        $base = self::baseDirectory();
        // Ancestrais ausentes da base são criados um a um, cada um com 0750 explícito; os já existentes
        // (geridos pelo operador) não são alterados.
        $ausentes = [];
        for ($p = rtrim($base, '/\\'); $p !== '' && !file_exists($p) && !is_link($p); $p = dirname($p)) {
            array_unshift($ausentes, $p);
            if (dirname($p) === $p) {
                break;
            }
        }
        foreach ($ausentes as $ausente) {
            if (!$this->criarPasta($ausente)) {
                return null;
            }
        }
        if (!is_dir($base)) {
            $this->fail('Não foi possível criar a pasta de destino.');
            return null;
        }
        $atual = realpath($base);
        if ($atual === false) {
            $this->fail('Pasta de destino inválida.');
            return null;
        }

        // Desce componente a componente: cada ancestral existente é resolvido (links inclusive) e conferido
        // dentro da base antes de qualquer mkdir, para que um link simbólico não crie pastas fora dela.
        foreach ($pasta === '' ? [] : explode('/', $pasta) as $componente) {
            if ($componente === '') {
                continue;
            }
            $proximo = $atual . DIRECTORY_SEPARATOR . $componente;
            if (!file_exists($proximo) && !is_link($proximo)) {
                if (!$this->criarPasta($proximo)) {
                    return null;
                }
            }
            $real = realpath($proximo);
            if ($real === false || !is_dir($real) || !$this->dentroDoBase($real)) {
                $this->fail('Pasta de destino inválida.');
                return null;
            }
            $atual = $real;
        }

        return $atual;
    }

    /**
     * Cria uma pasta com 0750 explícito (independe do umask). Se o modo não puder ser aplicado, a pasta
     * recém-criada é removida, para que a próxima tentativa a recrie em vez de herdar o modo errado.
     * Pasta criada em paralelo por outro processo é aceita como está.
     */
    private function criarPasta(string $caminho): bool
    {
        if (!@mkdir($caminho, 0750)) {
            if (is_dir($caminho)) {
                return true;
            }
            return $this->fail('Não foi possível criar a pasta de destino.');
        }
        if (!$this->aplicarPermissao($caminho, 0750)) {
            @rmdir($caminho);
            return $this->fail('Não foi possível aplicar as permissões da pasta de destino.');
        }

        return true;
    }

    private function dentroDoBase(string $caminho): bool
    {
        $base = realpath(self::baseDirectory());
        $real = realpath($caminho);
        if ($base === false || $real === false) {
            return false;
        }

        return $real === $base || strpos($real, $base . DIRECTORY_SEPARATOR) === 0;
    }

    private function fail(string $mensagem): bool
    {
        $this->msgError[] = $mensagem;
        $this->arqError = 1;
        return false;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O tamanho do arquivo enviado é maior que o limite!',
            UPLOAD_ERR_PARTIAL => 'O envio do arquivo foi interrompido.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            default => 'Falha no envio do arquivo.',
        };
    }
}
