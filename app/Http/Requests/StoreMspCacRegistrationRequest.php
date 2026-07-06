<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMspCacRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public submission by existing MSPs
    }

    public function rules(): array
    {
        return [
            'phoneNumber' => ['required', 'digits:11'],
            'email'       => ['nullable', 'email', 'max:255'],

            // Bio data (matches the msps table)
            'firstName'   => ['required', 'string', 'max:255'],
            'lastName'    => ['required', 'string', 'max:255'],
            'otherNames'  => ['nullable', 'string', 'max:255'],
            'gender'      => ['required', Rule::in(['Male', 'Female'])],
            'dateOfBirth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'state'       => ['required'],
            'lga'         => ['required'],
            'cohort'      => ['required', Rule::in(['Year 1', 'Year 2'])],
            'nin'         => ['required', 'digits:11'],

            // Documents — nullable here; "required unless already on file" is
            // enforced in the controller so returning MSPs needn't re-upload.
            'validIdType'   => ['required', Rule::in(['passport', 'drivers_license', 'voters_card', 'national_id'])],
            'validId'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'passportPhoto' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'signature'     => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],

            // Business
            'businessAddress' => ['required', 'string', 'max:500'],
            'businessName1' => ['required', 'string', 'max:255', 'different:businessName2', 'different:businessName3'],
            'businessName2' => ['required', 'string', 'max:255', 'different:businessName3'],
            'businessName3' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'validId.mimes'       => 'Upload a valid ID as JPG, PNG or PDF.',
            'passportPhoto.mimes' => 'Passport photo must be a JPG or PNG image.',
            'signature.mimes'     => 'Signature must be a JPG or PNG image.',
            'businessName1.different' => 'The three proposed business names must be different.',
            'businessName2.different' => 'The three proposed business names must be different.',
        ];
    }
}