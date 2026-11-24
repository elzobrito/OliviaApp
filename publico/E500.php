<?php

namespace OliviaPublico;

use OliviaPublico\Components\Feedback\ErrorState;
use OliviaPublico\View\ViewModelErro;

/**
 * Página de erro genérica usada pelo OliviaLib\ErrorHandler para 403, 405 e 500 (OLIVIA-SEC-322).
 *
 * Parâmetros: status, title, description e detail (só preenchido com APP_DEBUG=true).
 */
class E500 extends ViewModelErro
{
    public function config()
    {
        $this->setNivel($this->parametros['nivel'] ?? '..');
        $this->setTitlePage((string) ($this->parametros['status'] ?? 500) . ' - Olívia');
    }

    public function content()
    {
        $this->renderComponent(ErrorState::class, [
            'code' => (string) ($this->parametros['status'] ?? 500),
            'title' => (string) ($this->parametros['title'] ?? 'Erro interno'),
            'description' => (string) ($this->parametros['description'] ?? 'Algo deu errado ao processar a requisição.'),
            'detail' => $this->parametros['detail'] ?? null,
            'href' => './',
            'label' => 'Voltar para o início',
        ]);
    }
}
