<?php


namespace OliviaPublico\View;

use OliviaLib\CommandController;
use OliviaPublico\Components\Component;

abstract class ViewModel extends CommandController
{

    abstract function sidebar_menu();
    abstract function navbar_menu();
    abstract function main_footer();
    abstract function content();
    abstract function config();

    private $mapLink;
    private $titlePage;
    public $parametros = null;
    public static $instance = null;
    private $nivel;

    public function __construct($parametros = null)
    {
        if ($parametros != null)
            $this->parametros = $parametros;

        if (null === self::$instance)
            self::$instance = $this->index();

        return self::$instance;
    }

    private function index()
    {
        $this->head();
        $this->body();
    }

    protected function renderComponent(string $componentClass, array $props = [])
    {
        /** @var Component $component */
        $component = new $componentClass($props);
        $component->render();
    }


    /**
     * Set the value of mapLink
     *
     * @return  self
     */
    public function setNivel($nivel)
    {
        $this->nivel = $nivel;
    }

    /**
     * Get the value of mapLink
     */
    public function getMapLink()
    {
        return $this->mapLink;
    }

    /**
     * Set the value of mapLink
     *
     * @return  self
     */
    public function setMapLink($mapLink)
    {
        $this->mapLink = $mapLink;
    }


    /**
     * Get the value of titlePage
     */
    public function getTitlePage()
    {
        return $this->titlePage;
    }

    /**
     * Set the value of titlePage
     *
     * @return  self
     */
    public function setTitlePage($titlePage)
    {
        $this->titlePage = $titlePage;
    }


    private function head()
    {
        $this->config();
?>
        <!DOCTYPE html>
        <html lang="pt-br" class="h-100">

        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Olívia</title>
            <style>
                :root {
                    color-scheme: dark;
                    --bg: #0d1321;
                    --bg-soft: #1d2d44;
                    --panel: rgba(255, 255, 255, 0.08);
                    --panel-border: rgba(255, 255, 255, 0.12);
                    --text: #f0ebd8;
                    --muted: #c7d0dd;
                    --accent: #748cab;
                    --accent-strong: #f4a261;
                    --shadow: 0 24px 80px rgba(0, 0, 0, 0.28);
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    min-height: 100vh;
                    font-family: "Segoe UI", sans-serif;
                    background:
                        radial-gradient(circle at top left, rgba(116, 140, 171, 0.28), transparent 34%),
                        linear-gradient(135deg, var(--bg) 0%, #101a2f 45%, var(--bg-soft) 100%);
                    color: var(--text);
                }

                a {
                    color: inherit;
                    text-decoration: none;
                }

                .page-shell {
                    width: min(1120px, calc(100% - 32px));
                    margin: 0 auto;
                    padding: 32px 0 40px;
                }

                .app-header,
                .app-footer,
                .hero-grid,
                .error-card {
                    backdrop-filter: blur(18px);
                    background: var(--panel);
                    border: 1px solid var(--panel-border);
                    box-shadow: var(--shadow);
                }

                .app-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    gap: 24px;
                    padding: 24px 28px;
                    border-radius: 28px;
                }

                .eyebrow,
                .hero-kicker,
                .panel-label,
                .error-code {
                    margin: 0 0 8px;
                    text-transform: uppercase;
                    letter-spacing: 0.18em;
                    font-size: 0.72rem;
                    color: var(--accent-strong);
                }

                .brand-title,
                .hero-copy h2,
                .error-card h1 {
                    margin: 0;
                    line-height: 1.05;
                }

                .brand-tagline,
                .hero-description,
                .error-card p,
                .app-footer span {
                    color: var(--muted);
                }

                .nav-links {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 12px;
                }

                .nav-links a,
                .button-primary,
                .button-secondary {
                    border-radius: 999px;
                    padding: 12px 18px;
                    border: 1px solid transparent;
                    transition: 0.2s ease;
                }

                .nav-links a {
                    background: rgba(255, 255, 255, 0.05);
                    border-color: rgba(255, 255, 255, 0.08);
                }

                .nav-links a:hover,
                .button-secondary:hover {
                    border-color: rgba(255, 255, 255, 0.2);
                    transform: translateY(-1px);
                }

                .hero-grid {
                    margin-top: 24px;
                    border-radius: 36px;
                    padding: 32px;
                    display: grid;
                    grid-template-columns: minmax(0, 1.65fr) minmax(280px, 0.9fr);
                    gap: 24px;
                }

                .hero-copy h2 {
                    font-size: clamp(2.5rem, 6vw, 4.8rem);
                    max-width: 12ch;
                }

                .hero-description {
                    font-size: 1.05rem;
                    max-width: 56ch;
                    margin: 20px 0;
                }

                .badge-row,
                .hero-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 12px;
                }

                .badge-row span {
                    border-radius: 999px;
                    padding: 8px 14px;
                    background: rgba(244, 162, 97, 0.12);
                    color: #ffd2ab;
                    border: 1px solid rgba(244, 162, 97, 0.24);
                }

                .hero-actions {
                    margin-top: 24px;
                }

                .button-primary {
                    background: var(--accent-strong);
                    color: #1a1a1a;
                    font-weight: 700;
                }

                .button-secondary {
                    background: transparent;
                    border-color: rgba(255, 255, 255, 0.16);
                }

                .hero-panel {
                    border-radius: 28px;
                    padding: 24px;
                    background: rgba(0, 0, 0, 0.18);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                }

                .hero-panel h3 {
                    margin-top: 0;
                }

                .hero-panel ul {
                    margin: 18px 0 0;
                    padding-left: 18px;
                    color: var(--muted);
                }

                .hero-panel li + li {
                    margin-top: 10px;
                }

                .app-footer {
                    margin-top: 24px;
                    padding: 20px 24px;
                    border-radius: 24px;
                    display: flex;
                    justify-content: space-between;
                    gap: 16px;
                    flex-wrap: wrap;
                }

                .error-shell {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }

                .error-card {
                    width: min(620px, 100%);
                    border-radius: 32px;
                    padding: 40px 32px;
                    text-align: center;
                }

                @media (max-width: 860px) {
                    .app-header,
                    .hero-grid,
                    .app-footer {
                        padding: 24px;
                    }

                    .hero-grid {
                        grid-template-columns: 1fr;
                    }
                }

                @media (max-width: 640px) {
                    .page-shell {
                        width: min(100% - 20px, 1120px);
                        padding-top: 20px;
                    }

                    .app-header {
                        flex-direction: column;
                    }
                }
            </style>
        </head>
    <?php
    }

    private function body()
    {
    ?>

        <body class="d-flex h-100 text-center text-white bg-dark">
            <div class="page-shell">
                <?php
                $this->navbar_menu();
                $this->content();
                $this->main_footer();
                ?>
            </div>
        </body>

        </html>
<?php
    }
}
