<?php

namespace OliviaPublico\Login;

use OliviaPublico\View\ViewModelLogin;

class LostPassword extends ViewModelLogin
{
    public function config()
    {
        $this->setNivel($this->parametros['nivel']);
    }

    public function content()
    {
?>
        <section class="d-flex align-items-center my-5 mt-lg-6 mb-lg-5">
            <div class="container">
                <div class="row justify-content-center form-bg-image" data-background-lg="../../assets/img/illustrations/signin.svg">
                    <div class="col-12 d-flex align-items-center justify-content-center">
                        <div class="bg-white shadow-soft border rounded border-light p-4 p-lg-5 w-100 fmxw-500">
                            <div class="text-center text-md-center mb-4 mt-md-0">
                                <h1 class="mb-0 h3">Digite suas credenciais</h1>
                            </div>
                            <form action="<?= $this->parametros['pre_url'] .   '-recuperar-senha' ?>" method="post" class="mt-4">
                                <?= $this->csrf_field(); ?>
                                <!-- Form -->
                                <div class="form-group mb-4">
                                    <label for="email">Seu Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="basic-addon1"><span class="fas fa-id-card"></span></span>
                                        <input type="email" class="form-control" placeholder="nome@provedor" id="email" name="email" autofocus required>
                                    </div>
                                </div>
                                <!-- End of Form -->
                                <button type="submit" class="btn btn-block btn-primary">Recuperar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
<?php
    }
}
