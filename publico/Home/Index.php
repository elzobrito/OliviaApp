<?php

namespace OliviaPublico\Home;

use OliviaPublico\Components\Footers\SimpleFooter;
use OliviaPublico\Components\Navigation\BrandHeader;
use OliviaPublico\Components\Sections\FeatureGrid;
use OliviaPublico\Components\Sections\HeroSection;
use OliviaPublico\View\ViewModel;

class Index extends ViewModel
{
    public function config()
    {
        $this->setNivel($this->parametros['nivel']);
    }
    public function sidebar_menu()
    {
    }

    public function navbar_menu()
    {
        $this->renderComponent(BrandHeader::class, [
            'brand' => 'Olivia',
            'tagline' => 'Framework PHP enxuto para PHP 8.2 a 8.5, com as proteções ligadas por padrão.',
            'links' => [
                ['label' => 'Início', 'href' => './'],
                ['label' => 'Erro 404', 'href' => './error404'],
            ],
        ]);
    }

    public function main_footer()
    {
        $this->renderComponent(SimpleFooter::class, [
            'text' => 'Olivia Framework',
            'caption' => 'Rotas, controllers, models e UI componentizada, com segurança desde o primeiro request.',
        ]);
    }

    public function content()
    {
        $this->renderComponent(HeroSection::class, [
            'kicker' => 'Seguro por padrão',
            'title' => 'Do roteador ao banco, protegido sem configuração extra.',
            'description' => 'O Olivia reúne roteador, controllers, models e views em componentes. CSRF, sessão endurecida, banco configurado pelo ambiente e criptografia autenticada já vêm ligados: o que falta configurar falha fechado, em vez de cair num padrão inseguro.',
            'badges' => [
                'PHP 8.2–8.5',
                'Router',
                'CSRF',
                'AES-256-GCM',
                'PSR-4',
            ],
            'actions' => [
                ['label' => 'Ver rota inicial', 'href' => './', 'variant' => 'primary'],
                ['label' => 'Testar página 404', 'href' => './error404', 'variant' => 'secondary'],
            ],
            'panelLabel' => 'Novidades',
            'panelTitle' => 'O que chegou no endurecimento',
            'panelItems' => [
                'Banco configurado só pelo ambiente: sem credencial no código nem no DSN.',
                'Token CSRF só no corpo do POST ou no header, nunca na URL.',
                'Criptografia AES-256-GCM com contexto; CBC legado só quando pedido.',
                'Uploads conferem o conteúdo real dos arquivos Office e ODF.',
                'Redirect de 404 relativo ao host, sem redirecionamento aberto.',
            ],
        ]);

        $this->renderComponent(FeatureGrid::class, [
            'kicker' => 'Recursos',
            'title' => 'Tudo o que vem no kernel',
            'description' => 'Bibliotecas incorporadas ao repositório, com um único pin de PHP e testes de comportamento cobrindo cada parte.',
            'items' => [
                [
                    'title' => 'Roteador e middlewares',
                    'text' => 'Rotas por método HTTP e padrão de URL, middlewares por rota e página de erro com status 404.',
                    'tags' => ['Router', 'Middleware'],
                ],
                [
                    'title' => 'CSRF e sessão',
                    'text' => 'Token aleatório por sessão comparado com hash_equals, via campo _token ou header X-CSRF-Token. Cookie HttpOnly por padrão e Secure em HTTPS. O método regenerateSession() renova a sessão no login.',
                    'tags' => ['csrf_field()', 'Sessão'],
                ],
                [
                    'title' => 'Banco de dados',
                    'text' => 'MySQL e SQLite configurados por OLIVIA_DB_DSN ou DB_*, com conexões nomeadas, TLS fora do loopback e erro claro quando falta configuração.',
                    'tags' => ['MySQL', 'SQLite', 'TLS'],
                ],
                [
                    'title' => 'Conexões e transações',
                    'text' => 'Pool que reaproveita o PDO por configuração, transações aninhadas com rollback e reconexão segura só para leituras.',
                    'tags' => ['Pool', 'Transações'],
                ],
                [
                    'title' => 'QueryBuilder',
                    'text' => 'Tabelas, colunas, ORDER BY e LIMIT validados; valores do usuário só por placeholder; Raw::sql para SQL confiável.',
                    'tags' => ['SQL', 'Placeholders'],
                ],
                [
                    'title' => 'Models',
                    'text' => 'CRUD com proteção contra atribuição em massa e delete que recusa a operação quando não há identificador.',
                    'tags' => ['CRUD', 'Mass assignment'],
                ],
                [
                    'title' => 'Criptografia',
                    'text' => 'Crypto::encrypt e decrypt com AES-256-GCM e contexto no AAD: um valor cifrado para um contexto não abre em outro.',
                    'tags' => ['AES-256-GCM', 'AAD'],
                ],
                [
                    'title' => 'Upload',
                    'text' => 'Tipo conferido pelo conteúdo, estrutura de Office e ODF validada e pastas criadas com permissões restritas.',
                    'tags' => ['MIME', 'Storage'],
                ],
                [
                    'title' => 'Testes e CI',
                    'text' => 'Suítes de contrato e de comportamento, com servidor HTTP real, rodando no CI em PHP 8.5 e na imagem Alpine de produção.',
                    'tags' => ['behavior_test', 'CI'],
                ],
            ],
        ]);
    }
}
