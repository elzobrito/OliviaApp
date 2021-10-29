<?php

namespace OliviaLib;

use App\Model\Configuracoes;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Email
{

    //put your code here

    private $email;
    private $mensagem;
    private $assunto;
    private $anexos;
    private $anexos2;

    function __construct($email, $mensagem, $assunto, $anexos = null, $anexos2 = null)
    {
        $this->email = $email;
        $this->mensagem = $mensagem;
        $this->assunto = $assunto;
        $this->anexos = $anexos;
        $this->anexos2 = $anexos2;
    }

    function setAssunto($assunto)
    {
        $this->assunto = $assunto;
    }

    function setEmail($email)
    {
        $this->email = $email;
    }

    function setMensagem($mensagem)
    {
        $this->mensagem = $mensagem;
    }

    function setAnexos($anexos)
    {
        $this->anexos = $anexos;
    }

    public function enviar()
    {
        try {
            if ($this->email != "") {
                $mailer = new PHPMailer();
                $mailer->IsSMTP();
                $mailer->SMTPDebug = 0;
                $mailer->Port = 587; //Indica a porta de conexão para a saída de e-mails. Utilize obrigatoriamente a porta 587.
                $mailer->Host = 'mail.com.br'; //Onde em 'servidor_de_saida' deve ser alterado por um dos hosts abaixo:
                $mailer->SMTPAuth = true; //Define se haverá ou não autenticação no SMTP
                $mailer->Username = "email@servidor.com.br"; //Informe o e-mai o completo
                $mailer->Password = 'senha'; //Senha da caixa postal
                $mailer->FromName = '=?UTF-8?B?'.base64_encode('').'?='; //Nome que será exibido para o destinatário
                $mailer->From = "email@servidor.com.br"; //Obrigatório ser a mesma caixa postal indicada em "username"
                $mailer->AddAddress($this->email); //Destinatários
                $mailer->Subject = '=?UTF-8?B?'.base64_encode($this->assunto).'?=';
                $mailer->IsHTML(true); // Define que o e-mail será enviado como HTML
                $mailer->Body = $this->mensagem;
                $mailer->CharSet = "UTF-8";

                foreach ($this->anexos as $key => $arquivo) {
                    $mailer->addAttachment(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . $arquivo->src);
                }

                foreach ($this->anexos2 as $key => $arquivo) {
                    $mailer->addAttachment(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'publico' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . $arquivo->src);
                }

                $msg = "";
                if (!$mailer->Send()) {
                    $msg .= "Message was not sent";
                    $msg .=  "Mailer Error: " . $mailer->ErrorInfo;
                    return $msg;
                }
                
                return "E-mail enviado com sucesso !!!";
            }
        } catch (Exception $erro) {
            return 'erro' . $erro;
        }
    }
}
