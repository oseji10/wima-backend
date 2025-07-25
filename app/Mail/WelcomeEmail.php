<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use Illuminate\Contracts\Queue\ShouldQueue;

class WelcomeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $email;
    public $firstName;
    public $lastName;
    public $password;
    // public $languageId;
      /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($email, $firstName, $lastName, $password)
    {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->password = $password;
        // $this->languageId = $languageId;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.welcome-email')
                    ->subject('Welcome to WIMA')
                    ->with([
                        'email' => $this->email,
                        'firstName' => $this->firstName,
                        'lastName' => $this->lastName,
                        
                        'password' => $this->password,
                        // 'languageId' => $this->languageId,
                        'action_url' => "https://wimanigeria.com",
                        
                        'support_email' => "info@wimanigeria.com",
                    ]);
    }
}
