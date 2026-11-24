<?php

namespace OliviaPublico\Components\Sections;

use OliviaPublico\Components\Component;

/**
 * Grade de cartões de recursos.
 *
 * Props:
 *   - kicker      (string) rótulo curto acima do título
 *   - title       (string) título da seção
 *   - description (string) texto de apoio
 *   - items       (array)  [['title' => '', 'text' => '', 'tags' => ['...']], ...]
 */
class FeatureGrid extends Component
{
    public function render(): void
    {
        $kicker = (string) $this->prop('kicker', '');
        $title = (string) $this->prop('title', '');
        $description = (string) $this->prop('description', '');
        $items = $this->prop('items', []);

        echo '<section class="feature-section">';
        echo '<header class="feature-header">';

        if ($kicker !== '') {
            echo '<p class="hero-kicker">' . $this->e($kicker) . '</p>';
        }
        if ($title !== '') {
            echo '<h2>' . $this->e($title) . '</h2>';
        }
        if ($description !== '') {
            echo '<p class="feature-description">' . $this->e($description) . '</p>';
        }

        echo '</header>';

        if (is_array($items) && $items !== []) {
            echo '<div class="feature-grid">';

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                echo '<article class="feature-card">';
                echo '<h3>' . $this->e((string) ($item['title'] ?? '')) . '</h3>';
                echo '<p>' . $this->e((string) ($item['text'] ?? '')) . '</p>';

                $tags = $item['tags'] ?? [];
                if (is_array($tags) && $tags !== []) {
                    echo '<div class="feature-tags">';
                    foreach ($tags as $tag) {
                        echo '<span>' . $this->e((string) $tag) . '</span>';
                    }
                    echo '</div>';
                }

                echo '</article>';
            }

            echo '</div>';
        }

        echo '</section>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
