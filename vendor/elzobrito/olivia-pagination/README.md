# Olivia Pagination

Biblioteca simples de paginacao em PHP para calcular `offset/limit` e renderizar links de navegacao.

O projeto foi mantido com compatibilidade com a API original, entao o uso legado continua funcionando, com correcoes internas no calculo de paginas, na faixa "Mostrando X ao Y de Z" e na montagem das URLs.

## Requisitos

- PHP 7.1 ou superior
- Composer

## Instalacao

```bash
composer require elzobrito/olivia-pagination
```

Se estiver usando o projeto localmente:

```bash
composer install
```

## Autoload

```php
require __DIR__ . '/vendor/autoload.php';
```

Namespace da classe:

```php
use OliviaPagination\Pagination;
```

## Uso Basico

Exemplo com o fluxo compativel com a versao atual:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use OliviaPagination\Pagination;

$pagination = new Pagination(10, 'page');
$pagination->set_total(95);

$offset = $pagination->get_start();
$limit = $pagination->get_total_perPage();

// Exemplo de SQL:
// SELECT * FROM produtos LIMIT {$offset}, {$limit}

echo $pagination->page_links('produtos.php');
```

## Metodos Principais

### `__construct($perPage, $instance, $customCSS = '')`

Cria a paginacao.

- `$perPage`: quantidade de itens por pagina
- `$instance`: nome do parametro GET que identifica a pagina atual
- `$customCSS`: classes CSS extras adicionadas ao grupo de botoes

Exemplo:

```php
$pagination = new Pagination(20, 'pagina', 'my-custom-class');
```

### `set_total($totalRows)`

Define o total de registros da consulta.

```php
$pagination->set_total(250);
```

### `get_start()`

Retorna o `offset` calculado com base na pagina atual.

```php
$offset = $pagination->get_start();
```

### `get_total_perPage()`

Retorna a quantidade de itens por pagina.

```php
$perPage = $pagination->get_total_perPage();
```

### `get_limit()`

Retorna uma string no formato usado com frequencia em `LIMIT` no MySQL.

```php
$limit = $pagination->get_limit(); // exemplo: "0,10"
```

### `get_limit_keys()`

Retorna um array com `offset` e `limit`.

```php
$params = $pagination->get_limit_keys();

// [
//   'offset' => 0,
//   'limit' => 10
// ]
```

### `page_links($path = '?', $filter = null)`

Renderiza o HTML da navegacao.

- `$path`: caminho base da pagina
- `$filter`: array opcional com filtros extras para manter na URL

Exemplo simples:

```php
echo $pagination->page_links('clientes.php');
```

Exemplo com filtros:

```php
echo $pagination->page_links('clientes.php', [
    'status' => 'ativo',
    'ordem' => 'nome'
]);
```

Resultado esperado nas URLs:

```text
clientes.php?status=ativo&ordem=nome&page=2
```

## Exemplo Completo

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use OliviaPagination\Pagination;

$pagination = new Pagination(10, 'page');

// Em um cenario real, esse valor viria de:
// SELECT COUNT(*) FROM produtos
$totalProdutos = 95;
$pagination->set_total($totalProdutos);

$limit = $pagination->get_limit_keys();

// Exemplo de consulta:
// SELECT * FROM produtos LIMIT :offset, :limit
//
// $stmt->bindValue(':offset', $limit['offset'], PDO::PARAM_INT);
// $stmt->bindValue(':limit', $limit['limit'], PDO::PARAM_INT);

echo $pagination->page_links('produtos.php', [
    'categoria' => 'livros'
]);
```

## Metodos Novos Opcionais

Esses metodos foram adicionados na refatoracao para facilitar integracao e testes, sem quebrar o uso anterior.

### `get_current_page()`

Retorna a pagina atual.

```php
$currentPage = $pagination->get_current_page();
```

### `get_total_pages()`

Retorna o total de paginas calculado.

```php
$totalPages = $pagination->get_total_pages();
```

### `has_previous_page()`

Indica se existe pagina anterior.

```php
if ($pagination->has_previous_page()) {
    // ...
}
```

### `has_next_page()`

Indica se existe proxima pagina.

```php
if ($pagination->has_next_page()) {
    // ...
}
```

### `set_current_page($page)`

Permite definir a pagina manualmente, util para testes ou para integracao fora do fluxo padrao de `$_GET`.

```php
$pagination->set_current_page(3);
```

### `to_array()`

Retorna o estado consolidado da paginacao.

```php
print_r($pagination->to_array());
```

Exemplo de retorno:

```php
Array
(
    [current_page] => 3
    [per_page] => 10
    [total_rows] => 95
    [total_pages] => 10
    [offset] => 20
    [limit] => 10
    [has_previous_page] => 1
    [has_next_page] => 1
)
```

## Compatibilidade

O foco da refatoracao foi preservar a API publica existente.

Mantido:

- `Pagination` no mesmo namespace
- assinaturas publicas antigas
- retorno em HTML no metodo `page_links()`
- suporte a classes Bootstrap e `$customCSS`

Melhorado internamente:

- calculo correto do total de paginas
- faixa "Mostrando X ao Y de Z"
- validacao basica de valores invalidos
- construcao de URL com filtros
- HTML mais consistente para os links

## Observacoes

- A renderizacao atual usa classes Bootstrap como `btn`, `btn-primary`, `row` e `col-*`.
- Para uso visual correto, recomenda-se carregar Bootstrap no projeto consumidor.
- `get_limit()` retorna string no formato comum do MySQL. Se estiver usando query preparada com PDO, prefira `get_limit_keys()`.

## Licenca

MIT
