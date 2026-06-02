<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AfastamentoAlerta extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $sucessos,
        public array $erros
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Alerta do envio de afastamento',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.afastamento-alerta',
        );
    }
}
