<?php

namespace OliviaPublico;

use OliviaPublico\View\ViewModelErro;

class E404 extends ViewModelErro
{
    public function config()
    {
        $this->setNivel($this->parametros['nivel']);
    }

    public function content()
    {
?>
        <section class="vh-100 d-flex align-items-center justify-content-center">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center d-flex align-items-center justify-content-center">
                        <div>
                            <img class="img-fluid w-75" src="./publico/assets/img/illustrations/404.svg" alt="404 not found">
                            <h1 class="mt-5">Página não <span class="font-weight-bolder text-primary">encontrada</span></h1>
                            <p class="lead my-4">Oops! Parece que a página que você está tentando acessar não existe.</p>
                            <p class="lead my-4"><a href="./home">Voltar</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <script>
            function goBack() {
                window.history.back();
            }
        </script>
<?php
    }
}
