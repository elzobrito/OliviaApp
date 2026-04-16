<?php

namespace OliviaPublico\Components\Sections;

use OliviaPublico\Components\Component;

class HeroSection extends Component
{
    public function render(): void
    {
        $title = $this->prop('title', 'Olivia Framework');
        $description = $this->prop('description', '');
        $badges = $this->prop('badges', []);
        $actions = $this->prop('actions', []);
        $panelTitle = $this->prop('panelTitle', 'Estrutura pronta para crescer');
        $panelItems = $this->prop('panelItems', []);

        echo '<section class="hero-grid">';
        echo '<div class="hero-copy">';
        echo '<span class="hero-kicker">Base completa</span>';
        echo '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        echo '<p class="hero-description">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';

        if (!empty($badges)) {
            echo '<div class="badge-row">';

            foreach ($badges as $badge) {
                echo '<span>' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            echo '</div>';
        }

        if (!empty($actions)) {
            echo '<div class="hero-actions">';

            foreach ($actions as $action) {
                $label = isset($action['label']) ? $action['label'] : '';
                $href = isset($action['href']) ? $action['href'] : '#';
                $variant = isset($action['variant']) ? $action['variant'] : 'primary';
                echo '<a class="button-' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            }

            echo '</div>';
        }

        echo '</div>';

        echo '<aside class="hero-panel">';
        echo '<p class="panel-label">Componentes</p>';
        echo '<h3>' . htmlspecialchars($panelTitle, ENT_QUOTES, 'UTF-8') . '</h3>';

        if (!empty($panelItems)) {
            echo '<ul>';

            foreach ($panelItems as $item) {
                echo '<li>' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</li>';
            }

            echo '</ul>';
        }

        echo '</aside>';
        echo '</section>';
    }
}
