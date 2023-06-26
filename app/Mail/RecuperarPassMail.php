<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Config;
class RecuperarPassMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $mail;
    public $token;
    public $empre;
    public $sender_mail;
    public $sender_name;

    public function __construct(
        $email,
        $token,
        $empre,
        $sender_mail, $sender_name
    )
    {
        $this->email = $email;
        $this->token = $token;
        $this->empre = $empre;
        $this->sender_mail = $sender_mail;
        $this->sender_name = $sender_name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->sender_mail, $this->sender_name)
            ->subject('Solicitud recuperacion contraseña ('. $this->empre. ')')
            ->markdown('mailViews.solicitudRecuperar')
            ->with([
                'token' => $this->token,
                'nomEmpresa' => $this->empre
            ]);
    }
}
