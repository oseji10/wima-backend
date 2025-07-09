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
                    ->subject('Application Started - FCT College of Nursing Sciences')
                    ->with([
                        'email' => $this->email,
                        'firstName' => $this->firstName,
                        'lastName' => $this->lastName,
                        
                        'password' => $this->password,
                        // 'languageId' => $this->languageId,
                        'action_url' => "https://fctson.abj.gov.ng",
                        // 'login_url' => "https://nchf.resilience.ng/login",
                        
                        'support_email' => "info@fctson.abj.gov.ng",
                    ]);
    }
}
