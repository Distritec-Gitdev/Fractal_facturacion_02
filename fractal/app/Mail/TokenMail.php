<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TokenMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $token;
    public string $url;

    public function __construct(string $token, string $url)
    {
        $this->token = $token;
        $this->url   = $url;
    }

   public function build()
{
    return $this
        ->from(config('mail.from.address'), config('mail.from.name'))
        ->replyTo(config('mail.reply_to.address'), config('mail.reply_to.name'))
        ->subject('Código de acceso')
        ->markdown('emails.tokens.send')
        ->with([
            'token' => $this->token,
            'url'   => $this->url,
        ]);
}
}
