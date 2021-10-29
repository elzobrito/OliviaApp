<?php

namespace OliviaPublico\Home;

use OliviaPublico\View\ViewModel;

class Index extends ViewModel
{
    public function config()
    {
        $this->setNivel($this->parametros['nivel']);
    }
    public function sidebar_menu()
    {
    }

    public function navbar_menu()
    {
?>
        <header class="mb-auto">
            <div>
                <h3 class="float-md-left mb-0">Olívia</h3>
                <nav class="nav nav-masthead justify-content-center float-md-right">
                    <a class="nav-link active" aria-current="page" href="<?= $this->route('login', []) ?>">Login</a>
                </nav>
            </div>
        </header>
    <?php
    }

    public function main_footer()
    {
    ?>
        <footer class="mt-auto text-white-50">
            <p>Cover template for <a href="https://getbootstrap.com/" class="text-white">Bootstrap</a>, by <a href="https://twitter.com/mdo" class="text-white">@mdo</a>.</p>
        </footer>
    <?php
    }

    public function content()
    {
    ?>
        <main class="px-3">
            <h1>Olívia Framework</h1>
            <p class="lead">É uma estrutura de aplicação web com sintaxe expressiva e elegante.</p>
        </main>
<?php
    }
}
