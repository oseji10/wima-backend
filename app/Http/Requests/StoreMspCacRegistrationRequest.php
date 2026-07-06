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
            // Identity (used to match the MSP in the msps table)
            'cohort'      => ['required', Rule::in(['Year 1', 'Year 2'])],
            'fullName'    => ['required', 'string', 'max:255'],
            'phoneNumber' => ['required', 'digits:11'],
            'nin'         => ['required', 'digits:11'],

            // 2. Valid ID
            'validIdType' => ['required', Rule::in(['passport', 'drivers_license', 'voters_card', 'national_id'])],
            'validId'     => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // 5 MB

            // 3. Passport photo
            'passportPhoto' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],

            // 4. Scanned signature
            'signature' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],

            // 5. Business address
            'businessAddress' => ['required', 'string', 'max:500'],

            // 6. Three proposed business names
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