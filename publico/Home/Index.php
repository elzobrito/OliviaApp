<?php

namespace OliviaPublico\Home;

use OliviaPublico\Components\Footers\SimpleFooter;
use OliviaPublico\Components\Navigation\BrandHeader;
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
            'tagline' => 'Um framework PHP enxuto, agora com composição de interface por componentes.',
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
            'caption' => 'Rotas, controllers, models e UI componentizada na mesma base.',
        ]);
    }

    public function content()
    {
        $this->renderComponent(HeroSection::class, [
            'title' => 'Framework completo usando componentes reutilizáveis.',
            'description' => 'A camada de views agora pode ser montada por blocos independentes, facilitando a evolução de layouts, páginas internas e bibliotecas de interface dentro do Olivia.',
            'badges' => [
                'MVC',
                'Router',
                'CSRF',
                'PSR-4',
            ],
            'actions' => [
                ['label' => 'Ver rota inicial', 'href' => './', 'variant' => 'primary'],
                ['label' => 'Testar página 404', 'href' => './error404', 'variant' => 'secondary'],
            ],
            'panelTitle' => 'O que entra nessa base componentizada',
            'panelItems' => [
                'Componente base com props para reutilização simples.',
                'Header, hero, footer e estado de erro separados por responsabilidade.',
                'Views mantendo a mesma convenção atual, mas renderizando componentes.',
            ],
        ]);
    }
}
