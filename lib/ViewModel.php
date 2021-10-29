<?php

namespace OliviaLib;

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
        <html lang="en" class="h-100">

        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Olívia</title>
            <!-- Bootstrap core CSS -->
            <link rel="stylesheet" href="./<?= $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel ?>/publico/css/bootstrap.min.css">
            <!-- Custom styles for this template -->
            <link rel="stylesheet" href="./<?= $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel ?>/publico/css/cover.css">

            <style>
                .bd-placeholder-img {
                    font-size: 1.125rem;
                    text-anchor: middle;
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    -ms-user-select: none;
                    user-select: none;
                }

                @media (min-width: 768px) {
                    .bd-placeholder-img-lg {
                        font-size: 3.5rem;
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
            <div class="cover-container d-flex w-100 h-100 p-3 mx-auto flex-column">
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
