# Olivia Framework

Olivia é um framework PHP para aplicações web com organização em MVC, roteamento simples, suporte a CSRF, integração com bibliotecas auxiliares do ecossistema Olivia e uma camada de views baseada em componentes reutilizáveis.

## Visão geral

O projeto foi estruturado para servir como base de aplicações web em PHP com:

- `controllers` para regras de entrada e fluxo
- `models` para acesso a dados
- `views` para renderização das páginas
- `components` para composição reutilizável da interface
- `router` centralizado para mapear URLs e ações

Na versão atual, a camada pública já usa componentes para montar a página inicial e a página de erro, facilitando a evolução do layout sem acoplar toda a interface a uma única classe.

## Requisitos

- PHP `^7.1` ou `^8.2.4`
- Composer
- Ambiente web compatível com `index.php` na raiz do projeto
- Sessão PHP habilitada

## Instalação

```bash
composer install
```

Depois, aponte seu servidor local para a pasta do projeto.

Exemplo com XAMPP:

1. Coloque o projeto em `htdocs`
2. Inicie Apache
3. Acesse a aplicação pelo navegador

## Estrutura do projeto

```text
OliviaApp/
|-- app/
|   |-- Controller/
|   |-- Middleware/
|   `-- Models/
|-- lib/
|   |-- CommandController.php
|   |-- Config.php
|   |-- Database.php
|   `-- Route.php
|-- publico/
|   |-- Components/
|   |-- Home/
|   |-- View/
|   `-- E404.php
|-- vendor/
|-- composer.json
|-- index.php
`-- README.md
```

## Namespaces

O autoload segue PSR-4:

- `OliviaApp\\` -> `app/`
- `OliviaLib\\` -> `lib/`
- `OliviaPublico\\` -> `publico/`

## Fluxo da aplicação

O ponto de entrada é [index.php](C:\xampp\htdocs\OliviaApp\index.php:1). Ele:

1. carrega o autoload do Composer
2. inicializa a configuração base da aplicação
3. executa o roteador principal

As rotas são registradas em [lib/Route.php](C:\xampp\htdocs\OliviaApp\lib\Route.php:1).

## Rotas atuais

Atualmente o projeto expõe as seguintes rotas base:

- `/` -> `HomeController#index`
- `/error404` -> `HomeController#error404`

Também existem rotas auxiliares montadas dinamicamente para testes com prefixo `olivia`.

## Camada de views e componentes

A renderização principal usa as classes base:

- [publico/View/ViewModel.php](C:\xampp\htdocs\OliviaApp\publico\View\ViewModel.php:1)
- [publico/View/ViewModelErro.php](C:\xampp\htdocs\OliviaApp\publico\View\ViewModelErro.php:1)

Essas classes agora oferecem suporte a componentes por meio do método `renderComponent()`.

Componentes disponíveis nesta base:

- [publico/Components/Component.php](C:\xampp\htdocs\OliviaApp\publico\Components\Component.php:1)
- [publico/Components/Navigation/BrandHeader.php](C:\xampp\htdocs\OliviaApp\publico\Components\Navigation\BrandHeader.php:1)
- [publico/Components/Sections/HeroSection.php](C:\xampp\htdocs\OliviaApp\publico\Components\Sections\HeroSection.php:1)
- [publico/Components/Footers/SimpleFooter.php](C:\xampp\htdocs\OliviaApp\publico\Components\Footers\SimpleFooter.php:1)
- [publico/Components/Feedback/ErrorState.php](C:\xampp\htdocs\OliviaApp\publico\Components\Feedback\ErrorState.php:1)

## Como criar uma nova página

1. Crie ou atualize um controller em `app/Controller`
2. Registre a rota em `lib/Route.php`
3. Crie uma view em `publico/`
4. Se necessário, crie componentes em `publico/Components`

Exemplo de fluxo:

- rota -> controller
- controller -> view
- view -> componentes

## Como criar um componente

Crie uma classe estendendo `OliviaPublico\Components\Component` e implemente o método `render()`.

Exemplo:

```php
<?php

namespace OliviaPublico\Components\Example;

use OliviaPublico\Components\Component;

class Card extends Component
{
    public function render(): void
    {
        echo '<div>';
        echo htmlspecialchars($this->prop('title', 'Sem título'), ENT_QUOTES, 'UTF-8');
        echo '</div>';
    }
}
```

Depois, renderize o componente em uma view:

```php
$this->renderComponent(\OliviaPublico\Components\Example\Card::class, [
    'title' => 'Meu componente',
]);
```

## Segurança

O projeto inicializa proteção CSRF na configuração base e armazena o token em sessão.

Arquivo principal de configuração:

- [lib/Config.php](C:\xampp\htdocs\OliviaApp\lib\Config.php:1)

Se você descobrir uma vulnerabilidade de segurança no framework, entre em contato com Elzo Brito pelo e-mail `elzo.santos@cps.sp.gov.br`.

## Dependências principais

- `elzobrito/olivia-router`
- `elzobrito/olivia-databaselibrary`
- `elzobrito/olivia-pagination`
- `elzobrito/olivia-pdf`
- `elzobrito/olivia-force-download`
- `ramsey/uuid`

## Próximos passos sugeridos

- criar layout principal com slots
- adicionar componentes de formulário e autenticação
- padronizar páginas CRUD usando a camada de componentes
- documentar middlewares e fluxo de models

## Licença

Olivia Framework é um software de código aberto licenciado sob a licença MIT.
