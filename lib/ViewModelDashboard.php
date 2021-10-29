<?php

namespace OliviaLib;

abstract class ViewModelDashboard extends CommandController
{

    abstract function sidebar_menu();
    abstract function navbar_menu();
    abstract function navbar_main();
    abstract function main_footer();
    abstract function content();
    abstract function config();

    private $mapLink;
    private $title;
    private $titlePage;
    private $subTitlePage;
    public $parametros = null;
    public static $instance = null;
    private $nivel;
    private $mainClass;

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
     * Set the value of MainClass
     *
     * @return  self
     */
    public function setmMainClass($mainClass)
    {
        $this->mainClass = $mainClass;
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
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set the value of title
     *
     * @return  self
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }



    /**
     * Get the value of titlePage
     */
    public function getSubTitlePage()
    {
        return $this->subTitlePage;
    }

    /**
     * Set the value of titlePage
     *
     * @return  self
     */
    public function setSubTitlePage($subTitlePage)
    {
        $this->subTitlePage = $subTitlePage;
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
            <title><?= $this->title; ?></title>
            <!-- Bootstrap core CSS -->

            <!-- Fontawesome -->
            <link type="text/css" href=".<?= DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ?>@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet">

            <!-- Notyf -->
            <link type="text/css" href=".<?= DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'notyf' . DIRECTORY_SEPARATOR ?>notyf.min.css" rel="stylesheet">

            <!-- Volt CSS -->
            <link type="text/css" href=".<?= DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR ?>volt.css" rel="stylesheet">
        </head>
        <?php
    }


    private function loadScript()
    {
        echo '
        <!-- Core -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'popper.js/dist/umd/popper.min.js"></script>
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bootstrap/dist/js/bootstrap.min.js"></script>

        <!-- Vendor JS -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'onscreen/dist/on-screen.umd.min.js"></script>

        <!-- Slider -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'nouislider/distribute/nouislider.min.js"></script>

        <!-- Jarallax -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'jarallax/dist/jarallax.min.js"></script>

        <!-- Smooth scroll -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'smooth-scroll/dist/smooth-scroll.polyfills.min.js"></script>

        <!-- Count up -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'countup.js/dist/countUp.umd.js"></script>

        <!-- Notyf -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'notyf/notyf.min.js"></script>

        <!-- Charts -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'chartist/dist/chartist.min.js"></script>
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'chartist-plugin-tooltips/dist/chartist-plugin-tooltip.min.js"></script>

        <!-- Datepicker -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'vanillajs-datepicker/dist/js/datepicker.min.js"></script>

        <!-- Simplebar -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'simplebar/dist/simplebar.min.js"></script>

        <!-- Volt JS -->
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js/volt.js"></script>';
    }

    private function body()
    {
        echo '<body>';
        $this->navbar_menu();
        $this->sidebar_menu();
        echo '<main class="' . $this->mainClass . '">';
        $this->navbar_main();

        if ($this->mapLink != null) {
        ?>
            <div class="py-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-dark breadcrumb-transparent">
                        <?php
                        foreach ($this->mapLink as $key => $link) {
                            echo '<li class="breadcrumb-item"><a href="' . $link['href'] . '">' . $link['text'] . '</a></li>';
                        }
                        ?>
                    </ol>
                </nav>
                <div class="d-flex justify-content-between w-100 flex-wrap">
                    <div class="mb-3 mb-lg-0">
                        <h1 class="h4"><?= $this->titlePage; ?></h1>
                        <p class="mb-0"><?= $this->subTitlePage; ?></p>
                    </div>
                </div>
            </div>
        <?php
        }

        echo '<div class="card border-light shadow-sm mb-4">';
        $this->content();
        echo '</div>';
        $this->main_footer();
        echo '</main>';
        $this->loadScript();
        echo '</body>';
        echo '</html>';
    }
}
