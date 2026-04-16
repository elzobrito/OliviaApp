# Olivia Force Download

Componente PHP leve para download e exibição inline de arquivos com suporte a streaming em chunks e Range Requests (download resumível).

## Requisitos

- PHP 7.1 ou superior
- Extensão `fileinfo` habilitada

## Instalação

```bash
composer require elzobrito/olivia-force-download
```

## Uso

### Download forçado

Força o navegador a baixar o arquivo em vez de exibi-lo.

```php
use OliviaDownload\Download;

$download = new Download();

// Usa o nome original do arquivo
$download->now('/caminho/para/relatorio.pdf');

// Define um nome personalizado (extensão preservada automaticamente)
$download->now('/caminho/para/relatorio.pdf', 'relatorio-janeiro');
// Resultado: relatorio-janeiro.pdf

// Nome personalizado com extensão explícita
$download->now('/caminho/para/dados.csv', 'exportacao.xlsx');
// Resultado: exportacao.xlsx
```

### Exibição inline

Exibe o arquivo diretamente no navegador. Útil para PDFs, imagens e outros formatos que o navegador consegue renderizar.

```php
$download->inline('/caminho/para/contrato.pdf');
```

## Funcionalidades

### Streaming em chunks

Arquivos são transmitidos em blocos de 8 KB, evitando estouro de memória mesmo com arquivos de centenas de megabytes.

### Download resumível (Range Requests)

Suporta o header HTTP `Range`, respondendo com `206 Partial Content`. Gerenciadores de download e navegadores aproveitam isso automaticamente para retomar transferências interrompidas.

### Detecção automática de MIME

O tipo MIME é detectado a partir do conteúdo real do arquivo via `mime_content_type()`, sem depender da extensão.

### Tratamento de erros

| Situação               | Código HTTP | Exceção                                  |
|------------------------|:-----------:|------------------------------------------|
| Arquivo não encontrado | 404         | `RuntimeException`                       |
| Sem permissão          | 403         | `RuntimeException`                       |
| Falha ao abrir         | —           | `RuntimeException`                       |

### Headers enviados

```
Content-Type: {mime detectado}
Content-Disposition: attachment|inline; filename="..."; filename*=UTF-8''...
Content-Length: {tamanho em bytes}
Content-Transfer-Encoding: binary
Accept-Ranges: bytes
Cache-Control: no-cache, must-revalidate
Pragma: no-cache
Expires: 0
```

## Exemplo com OliviaHorus

```php
use OliviaDownload\Download;

class RelatorioController
{
    public function exportar(): void
    {
        $arquivo = __DIR__ . '/../storage/relatorios/vendas-q1.pdf';

        $download = new Download();
        $download->now($arquivo, 'vendas-primeiro-trimestre');
    }

    public function visualizar(): void
    {
        $arquivo = __DIR__ . '/../storage/relatorios/vendas-q1.pdf';

        $download = new Download();
        $download->inline($arquivo);
    }
}
```

## Licença

MIT