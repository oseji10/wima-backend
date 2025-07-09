<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NicratWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

      /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($email, $firstName, $lastName, $roleName, $defaultPassword)
    {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->defaultPassword = $defaultPassword;
        
        $this->roleName = $roleName;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.nicrat-welcome-email')
                    ->subject('Welcome Email - National Cancer Health Fund')
                    ->with([
                        'email' => $this->email,
                        'firstName' => $this->firstName,
                        'lastName' => $this->lastName,
                        
                        'roleName' => $this->roleName,
                        
                        'defaultPassword' => $this->defaultPassword,
                        'action_url' => "https://nchf.resilience.ng/login",
                        'login_url' => "https://nchf.resilience.ng/login",
                        
                        'support_email' => "info@resilience.ng",
                    ]);
    }
}
