<?php

namespace OliviaPublico\Components\Feedback;

use OliviaPublico\Components\Component;

class ErrorState extends Component
{
    public function render(): void
    {
        $title = $this->prop('title', 'Página não encontrada');
        $description = $this->prop('description', 'A rota solicitada não existe ou ainda não foi publicada.');
        $href = $this->prop('href', './');
        $label = $this->prop('label', 'Voltar ao início');

        echo '<section class="error-shell">';
        echo '<div class="error-card">';
        echo '<p class="error-code">404</p>';
        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<a class="button-primary" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        echo '</div>';
        echo '</section>';
    }
}
