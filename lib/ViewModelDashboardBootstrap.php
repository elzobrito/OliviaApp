<?php

namespace OliviaLib;

abstract class ViewModelDashboardBootstrap extends CommandController
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
        echo '<!DOCTYPE html><html lang="pt-br" class="h-100"><head>
            <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>' . $this->title . '</title>
            <!-- Fontawesome -->
            <link type="text/css" href=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . '@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet">
            <!-- Bootstrap core CSS -->
            <link type="text/css" href=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'bootstrap.min.css" rel="stylesheet">
            <link type="text/css" href=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'dashboard.css" rel="stylesheet">
            <style>
                .bd-placeholder-img {
                    font-size: 1.125rem;
                    text-anchor: middle;
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    user-select: none;
                }
                @media (min-width: 768px) {
                    .bd-placeholder-img-lg {
                        font-size: 3.5rem;
                    }
                }
            </style>';
        $this->loadScript();
        echo '</head>';
    }

    public function breadscrumb()
    {
        echo '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        foreach ($this->mapLink as $key => $link) {
            echo '<li class="breadcrumb-item active" aria-current="page"><a href="' . $link['href'] . '">' . $link['text'] . '</a></li>';
        }
        echo '</ol></nav>';
    }

    private function loadScript()
    {
        echo '
        <!-- Core -->
        <script type="text/javascript" language="javascript" src="https://code.jquery.com/jquery-3.3.1.js"></script>
        <script src="' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'horus' . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . '/vendor/jquery/jquery.min.js"></script>
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js/bootstrap.bundle.min.js"></script>
        <script src=".' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR .  'js/F.jquery.js"></script>

        <link rel="stylesheet" href="' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'horus' . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . '/vendor/summernote/summernote-bs4.min.css">
        <script src="' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'horus' . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . '/vendor/summernote/summernote-bs4.min.js"></script>
        <link href="' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'horus' . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . '/vendor/select2/css/select2.css" rel="stylesheet" />
        <script src="' . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'horus' . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . '/vendor/select2/js/select2.min.js"></script>
        ';
    }

    private function body()
    {
        echo '<body>';
        $this->navbar_menu();
        $this->navbar_main();
        $this->sidebar_menu();
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">';
        $this->content();
        echo '</main>';
        $this->main_footer();
        echo '</body>';
        echo '</html>';
    }
}
