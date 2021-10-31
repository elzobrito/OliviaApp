<?php

namespace OliviaPublico\Login;

use OliviaPublico\View\ViewModelLogin;

class Index extends ViewModelLogin
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
                    <form action="<?= $this->parametros['pre_url'] . '-cadastrar-produto' ?>" method="post">
                        <?= $this->csrf_field() ?>
                        <div class="input-group mb-3">
                            <input type="number" class="form-control" placeholder="00000000000" name="cpf" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-envelope"></span>
                                </div>
                            </div>
                        </div>
                        <div class="input-group mb-3">
                            <input type="password" class="form-control" placeholder="Password" name="password" required>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <span class="fas fa-lock"></span>
                                </div>
                            </div>
                        </div>
                        <?= (isset($_SESSION['msg']['login_error']) ? '<div class="card-body"><div class="alert alert-danger" role="alert">' . $_SESSION['msg']['login_error'] . '</div></div>' : '') ?>
                        <?php
                        $_SESSION['msg']['login_error'] = null;
                        ?>
                        <div class="d-flex justify-content-between align-items-top mb-4">
                            <div><a href="<?= $this->route($this->parametros['pre_url'] . '-esqueci-senha', []) ?>" class="small text-right">Esqueceu a senha?</a></div>
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
