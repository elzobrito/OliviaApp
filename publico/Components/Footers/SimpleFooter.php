<?php

namespace OliviaPublico\Components\Footers;

use OliviaPublico\Components\Component;

class SimpleFooter extends Component
{
    public function render(): void
    {
        $text = $this->prop('text', 'Olivia Framework');
        $caption = $this->prop('caption', 'Aplicações web com rotas, views e componentes reutilizáveis.');

        echo '<footer class="app-footer">';
        echo '<strong>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</strong>';
        echo '<span>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</footer>';
    }
}
