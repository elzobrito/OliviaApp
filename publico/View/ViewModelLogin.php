<?php

namespace OliviaPublico\View;

use OliviaLib\CommandController;

abstract class ViewModelLogin extends CommandController
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
            <!-- Google Font: Source Sans Pro -->
            <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
            <!-- Font Awesome -->
            <link rel="stylesheet" href=".<?= DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel  . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'fontawesome-free' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'all.css' ?>">
            <!-- Theme style -->
            <link rel="stylesheet" href=".<?= DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel  . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'adminlte.min.css' ?>">
        </head>
    <?php
    }

    private function body()
    {
        $this->config();
    ?>

        <body class="hold-transition login-page">
            <!-- Site wrapper -->

            <?php
            $this->content();
            ?>
            <!-- /.login-box -->
            <!-- ./wrapper -->

            <!-- jQuery -->
            <script src=".<?= DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'jquery' . DIRECTORY_SEPARATOR . 'jquery.min.js' ?>"></script>
            <!-- Bootstrap 4 -->
            <script src=".<?= DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'bootstrap.bundle.min.js' ?>"></script>
            <!-- AdminLTE App -->
            <script src=".<?= DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . $this->nivel . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'adminlte.min.js' ?>"></script>

        </body>

        </html>
<?php
    }
}
