<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;

class UniqueCodeMail extends Mailable
{
    public function __construct(
        public string $fullName,
        public string $code,
        public string $category
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Unique Code for {$this->category} Registration"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.unique-code'
        );
    }
}