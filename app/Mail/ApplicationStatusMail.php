<?php

namespace App\Mail;

use App\Models\Membership; // Adjust to your model
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $membership;

    public function __construct(Membership $membership)
    {
        $this->membership = $membership;
    }

    public function build()
    {
        $statusText = ucfirst($this->membership->status);
        $greeting = $this->membership->fullName ? "Dear {$this->membership->fullName}," : 'Dear Applicant,';

        return $this->subject("Membership Application Status Update")
                    ->view('emails.application-status')
                    ->with([
                        'greeting' => $greeting,
                        'status' => $statusText,
                        'membershipType' => $this->membership->membershipType,
                        'fullName' => $this->membership->fullName,
                    ]);
    }
}