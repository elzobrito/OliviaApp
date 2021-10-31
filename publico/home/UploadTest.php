<?php

namespace OliviaPublico\Home;

use OliviaPublico\View\ViewModelLogin;

class UploadTest extends ViewModelLogin
{
    public function config()
    {
        $this->setNivel($this->parametros['nivel']);
    }

    public function content()
    {
?>
        <div class="login-box">
            <div class="login-logo">
                <a href="./"><b>Login</b>Olívia</a>
            </div>
            <!-- /.login-logo -->
            <div class="card">
                <div class="card-body login-card-body">
                    <p class="login-box-msg">Digite suas credenciais</p>
                    <form action="<?= $this->parametros['pre_url'] . '-upload-test' ?>" method="post" enctype="multipart/form-data">
                        <?= $this->csrf_field() ?>
                        <div class="form-group">
                            <label for="exampleInputFile">File input</label>
                            <div class="input-group">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="file" name="file">
                                    <label class="custom-file-label" for="exampleInputFile">Choose file</label>
                                </div>
                                <div class="input-group-append">
                                    <span class="input-group-text">Upload</span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <!-- /.col -->
                            <div class="col-4">
                                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                            </div>
                            <!-- /.col -->
                        </div>
                    </form>
                </div>
                <!-- /.login-card-body -->
            </div>
        </div>
<?php
    }
}
