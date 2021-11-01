<?php

namespace OliviaLib;

class CommandController
{
    private $parametros;
    private $arrays;

    private $array_object_temp;

    public function find_object($wheres, $values, $array)
    {
        $this->array_object_temp = $array;
        foreach ($wheres as $key => $value)
            $this->buscar_objeto_in_array($this->array_object_temp, $value, $values[$key]);

        $array_final = [];

        foreach ($this->array_object_temp as $key => $value) {
            $array_final[] = $value;
        }
        
        return $array_final;
    }

    public function buscar_objeto_in_array($array, $objeto, $valor)
    {
        foreach ($array as $key => $array_object)
            if ($array_object->$objeto != $valor) {
                unset($this->array_object_temp[$key]);
            }
    }

    public function letra_maiuscula($string)
    {
        $arr = explode(' ', $string);
        foreach ($arr as $key => $palavra){
            $palavra = strtolower($palavra);            
            $arr[$key] = ((strlen($palavra) > 3 ? ucfirst($palavra) : $palavra));
        }

        return implode(' ', $arr);
    }

    public function mes_do_ano_por_extenso($mes)
    {
        $numero = intval($mes);
        $meses[] = 'Janeiro';
        $meses[] = 'Fevereiro';
        $meses[] = 'Março';
        $meses[] = 'Abril';
        $meses[] = 'Maio';
        $meses[] = 'Junho';
        $meses[] = 'Julho';
        $meses[] = 'Agosto';
        $meses[] = 'Setembro';
        $meses[] = 'Outubro';
        $meses[] = 'Novembro';
        $meses[] = 'Dezembro';

        return $meses[$numero];
    }

    public function tirarAcentos($string)
    {
        return preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/", "/(ç)/", "/(Ç)/"), explode(" ", "a A e E i I o O u U n N c C"), $string);
    }

    function mask($mask, $str)
    {
        $str = preg_replace('/[^0-9]/is', '', $str);
        $str = str_replace(" ", "", $str);
        for ($i = 0; $i < strlen($str); $i++) 
            $mask[strpos($mask, "#")] = $str[$i];
        return $mask;
    }

    public function view($view, $parametros = null)
    {
        if ($_SESSION['CSRF'] == true)
            if ($parametros != null)
                $parametros += ['_token' => $this->csrf_token()];

        if ($parametros != null)
            $view = $view . '?' . http_build_query($parametros);
        header("Location: ." . DIRECTORY_SEPARATOR . $view);
    }

    public function route($view, $parametros = null)
    {
        if ($_SESSION['CSRF'] == true)
            $parametros += ['_token' => $this->csrf_token()];
        return '.' . DIRECTORY_SEPARATOR . $view . ($parametros != null ? '?' . http_build_query($parametros) : '');
    }

    public function url($view, $parametros = null)
    {
        return $view . ($parametros != null ? '?' . http_build_query($parametros) : '');
    }

    public function csrf_field()
    {
        return '<input type="hidden" name="_token" value="' . $_SESSION['UUID'] . '">';
    }

    public function csrf_token()
    {
        return $_SESSION['UUID'];
    }

    function validaCPF($cpf)
    {

        // Extrai somente os números
        $cpf = preg_replace('/[^0-9]/is', '', $cpf);

        // Verifica se foi informado todos os digitos corretamente
        if (strlen($cpf) != 11) {
            return false;
        }

        // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Faz o calculo para validar o CPF
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        return true;
    }

    function format_string($mask, $str, $ch = '#')
    {
        $c = 0;
        $rs = '';
        for ($i = 0; $i < strlen($mask); $i++)
            if ($mask[$i] == $ch) {
                $rs .= $str[$c];
                $c++;
            } else {
                $rs .= $mask[$i];
            }
        return $rs;
    }

    public function formataData($data, $d = null, $t = null)
    {
        if ($data != null) {
            $retorno = "";

            $dt = explode(" ", $data);
            $nd = implode("/", array_reverse(explode("-", $dt[0])));
            if (isset($dt[1]))
                $nt = $dt[1];

            if ($d != null) {
                $retorno .= $nd . " ";
            }

            if ($t != null) {
                $retorno .= substr($nt, 0, -3) . " ";
            }

            return $retorno;
        }
    }

    function getArrays()
    {
        return $this->arrays;
    }

    function setArrays($arrays)
    {
        $this->arrays = $this->requestArrayPost($arrays);
    }

    function getAllParametros()
    {
        return $this->parametros;
    }

    function getParametros($indice)
    {
        return $this->parametros[$indice];
    }

    function setParametros($nome = null)
    {
        $this->parametros = $this->requestArrayPost($nome != null ? $nome : 'params');
    }

    public function getDateTime($format)
    {
        $dateTime = new \DateTime();
        return $dateTime->format($format);
    }

    public function requestFile($dado)
    {
        return isset($_FILES[$dado]) ? $_FILES[$dado] : null;
    }

    public function requestArrayGet($dado)
    {
        return $data = filter_input(INPUT_GET, $dado, FILTER_DEFAULT, FILTER_FORCE_ARRAY);
    }

    public function requestArrayPost($dado)
    {
        return $data = filter_input(INPUT_POST, $dado, FILTER_DEFAULT, FILTER_FORCE_ARRAY);
    }

    public function requestPost($dado)
    {
        return filter_input(INPUT_POST, $dado);
    }

    public function requestGet($dado)
    {
        return filter_input(INPUT_GET, $dado);
    }

    function msgbox($mensagem, $janela, $atributos = null)
    {
        echo "<script>alert('" . $mensagem . "');window.location.href = './" . $janela . ($atributos != null ? "?" . $atributos : "") . "';</script>";
    }

    private function encrypt_decrypt($action, $string, $secret_key = null, $secret_iv = null)
    {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        // hash
        $key = hash('sha256', $secret_key ?? $_SESSION['secret_key']);
        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv ?? $_SESSION['secret_iv']), 0, 16);

        switch ($action) {
            case 'encrypt': {
                    $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
                    $output = base64_encode($output);
                    break;
                }

            case 'decrypt': {
                    $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
                    break;
                }
        }

        return $output;
    }

    function encryptIt($q, $secret_key = null, $secret_iv = null)
    {
        return $this->encrypt_decrypt('encrypt', $q, $secret_key, $secret_iv);
    }

    function decryptIt($q, $secret_key = null, $secret_iv = null)
    {
        return $this->encrypt_decrypt('decrypt', $q, $secret_key, $secret_iv);
    }
}
