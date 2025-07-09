<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $auto_password;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($patientEmail, $patientName, $appointmentDate, $appointmentTime, $doctorName)
    {
        $this->patientEmail = $patientEmail;
        $this->patientName = $patientName;
        $this->appointmentDate = $appointmentDate;
        $this->appointmentTime = $appointmentTime;
        $this->doctorName = $doctorName;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.appointment-email')
                    ->subject('Patient Appointment Schedule - Rachel Eye Clinic')
                    ->with([
                        'email' => $this->patientEmail,
                        'patient_name' => $this->patientName,
                        'appointment_date' => $this->appointmentDate,
                        'appointment_time' => $this->appointmentTime,
                        'doctor_name' => $this->doctorName,
                        'action_url' => "https://app.ilearnafricaedu.com/auth/signin/",
                        'login_url' => "https://app.ilearnafricaedu.com/auth/signin/",
                        
                        'support_email' => "info@racheleyeemr.com",
                    ]);
    }
}
