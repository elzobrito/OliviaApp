<?php

namespace OliviaPublico;

use OliviaPublico\Components\Feedback\ErrorState;
use OliviaPublico\View\ViewModelErro;

class E404 extends ViewModelErro
{
    public function config()
    {
        $this->setNivel($this->parametros['nivel']);
    }

    public function content()
    {
        $this->renderComponent(ErrorState::class, [
            'title' => 'Página não encontrada',
            'description' => 'A rota solicitada não foi localizada. Você pode voltar para a página inicial e continuar a navegação pelo framework.',
            'href' => './',
            'label' => 'Voltar para o início',
        ]);
    }
}
