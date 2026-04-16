<?php

namespace OliviaPublico\Components\Navigation;

use OliviaPublico\Components\Component;

class BrandHeader extends Component
{
    public function render(): void
    {
        $brand = $this->prop('brand', 'Olivia');
        $tagline = $this->prop('tagline');
        $links = $this->prop('links', []);

        echo '<header class="app-header">';
        echo '<div>';
        echo '<p class="eyebrow">Framework componentizado</p>';
        echo '<h1 class="brand-title">' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . '</h1>';

        if ($tagline !== null && $tagline !== '') {
            echo '<p class="brand-tagline">' . htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        echo '</div>';

        if (!empty($links)) {
            echo '<nav class="nav-links" aria-label="Navegação principal">';

            foreach ($links as $link) {
                $label = isset($link['label']) ? $link['label'] : '';
                $href = isset($link['href']) ? $link['href'] : '#';
                echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            }

            echo '</nav>';
        }

        echo '</header>';
    }
}
