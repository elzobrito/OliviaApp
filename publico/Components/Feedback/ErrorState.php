<?php

namespace OliviaPublico\Components\Feedback;

use OliviaPublico\Components\Component;

/**
 * Props: code (padrão "404"), title, description, href, label e detail (texto de depuração, opcional).
 */
class ErrorState extends Component
{
    public function render(): void
    {
        $title = $this->prop('title', 'Página não encontrada');
        $description = $this->prop('description', 'A rota solicitada não existe ou ainda não foi publicada.');
        $href = $this->prop('href', './');
        $label = $this->prop('label', 'Voltar ao início');
        $code = (string) $this->prop('code', '404');
        $detail = $this->prop('detail');

        echo '<section class="error-shell">';
        echo '<div class="error-card">';
        echo '<p class="error-code">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
        if (is_string($detail) && $detail !== '') {
            // Só chega aqui com APP_DEBUG=true (ErrorHandler); sempre escapado.
            echo '<pre class="error-detail">' . htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        }
        echo '<a class="button-primary" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        echo '</div>';
        echo '</section>';
    }
}
