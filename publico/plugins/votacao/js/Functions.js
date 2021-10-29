class Functions {

    static logs(text) {
        return text;
    }

    static getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(Functions.showPosition);
        } else {
            return "Geolocation is not supported by this browser.";
        }
    }

    static showPosition(position) {
        var x = '';
        x = position.coords.latitude + "," + position.coords.longitude;
        if (x != null) {
            return x;
        } else {
            return 'localização inválida';
        }
    }

    static checkMax(max, inputtype) {
        if ($("input[type='checkbox']").filter(':checked').length >= max) {
            inputtype.checked = false;
            return false;
        }
        $(".unidades").html($("input[type='checkbox']").filter(':checked').length);
    }
    static votaBranco() {
        $("input[type='checkbox']").each(function () {
            $(this).prop("checked", false);
        });
    }

    static buscarTipoAluno(id, _token) {
        var dados = null;
        var fd = new FormData();
        fd.append('_token', _token);
        fd.append('id_tipo', id);
        $.ajax({
            url: './buscar-tipo-aluno',
            data: fd,
            type: 'post',
            contentType: false,
            processData: false,
            success: function (response) {
                if (response !== null) {
                    dados = JSON.parse(response);
                    if (dados.length != 0) {
                        $("#id_tipoAluno").empty();
                        $("#id_tipoAluno").append($('<option value="" selected>Selecione...</option>'));
                        $.each(dados, function (i) {
                            $("#id_tipoAluno").append($('<option value="' + dados[i].id + '">' + dados[i].nome + '</option>'));
                        });
                    }
                }
            },
        });

    }

    showAlert() {
        var fd = new FormData();
        fd.append('_token', '<?= $this->csrf_token() ?>');
        $.ajax({
            url: './mail-new',
            data: fd,
            type: 'post',
            contentType: false,
            processData: false,
            success: function (response) {
                if (response !== null) {
                    dados = JSON.parse(response);
                    if (dados.length != 0) {
                        $.each(dados, function (id) {
                            $("#msgAlert").html('<div class="alert alert-warning alert-dismissible fade show" role="alert"><strong>Atenção!</strong> <br>Você tem uma nova mensagem <br><a class="btn btn-primary alert-link" href="./mail-read?id_mail=' + dados.id + '&_token=' + dados._token + '&tipo_caixa=' + dados.tipo_caixa + '">Abrir</a><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>');
                        });
                    }
                }
            },
        });
    }
}