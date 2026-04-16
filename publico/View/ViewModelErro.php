<?php

namespace OliviaPublico\View;

use OliviaLib\CommandController;
use OliviaPublico\Components\Component;

abstract class ViewModelErro extends CommandController
{

    abstract function content();
    abstract function config();

    private $mapLink;
    private $titlePage;
    private $nivel;
    public $parametros = null;
    public static $instance = null;

    public function __construct($parametros = null)
    {
        if ($parametros != null)
            $this->parametros = $parametros;

        if (null === self::$instance)
            self::$instance = $this->index();

        return self::$instance;
    }

    public function setNivel($nivel)
    {
        $this->nivel = $nivel;
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

        return $this;
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

        return $this;
    }


    private function head()
    {
        $this->config();
?>
        <!DOCTYPE html>
        <html lang="pt-br">

        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Olívia - Framework</title>
            <style>
                :root {
                    color-scheme: dark;
                    --bg: #0d1321;
                    --panel: rgba(255, 255, 255, 0.08);
                    --panel-border: rgba(255, 255, 255, 0.12);
                    --text: #f0ebd8;
                    --muted: #c7d0dd;
                    --accent: #f4a261;
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
                        radial-gradient(circle at top left, rgba(244, 162, 97, 0.18), transparent 32%),
                        linear-gradient(135deg, var(--bg) 0%, #13203a 100%);
                    color: var(--text);
                }

                a {
                    color: inherit;
                    text-decoration: none;
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
                    background: var(--panel);
                    border: 1px solid var(--panel-border);
                    box-shadow: var(--shadow);
                    backdrop-filter: blur(18px);
                }

                .error-code {
                    margin: 0 0 8px;
                    text-transform: uppercase;
                    letter-spacing: 0.2em;
                    font-size: 0.76rem;
                    color: var(--accent);
                }

                .error-card h1 {
                    margin: 0;
                    font-size: clamp(2rem, 5vw, 3.4rem);
                }

                .error-card p {
                    margin: 18px 0 24px;
                    color: var(--muted);
                    line-height: 1.6;
                }

                .button-primary {
                    display: inline-block;
                    border-radius: 999px;
                    padding: 12px 20px;
                    background: var(--accent);
                    color: #1a1a1a;
                    font-weight: 700;
                }
            </style>
        </head>
<?php
    }

    private function body()
    {
        $this->config();
        echo '<body>';
        $this->content();
        echo '</body></html>';
    }
}
