<?php

namespace App\Http\Requests;

use App\Models\GoTractApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoTractApplicationRequest extends FormRequest
{
    /**
     * Public application form — no authentication required to submit.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation keys are camelCase to match the payload sent by the React form,
     * so any validation errors come back keyed by the exact field names the
     * frontend uses to jump back to the offending step.
     */
    public function rules(): array
    {
        return [
            // Step 1 — Personal
            'fullName'    => ['required', 'string', 'max:255'],
            'gender'      => ['required', Rule::in(config('gotract.genders'))],
            'dateOfBirth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'phoneNumber' => ['required', 'digits:11'],
            'email'       => ['nullable', 'email', 'max:255'],
            'state'       => ['nullable', 'string', 'max:100'],
            'lga'         => ['required', Rule::in(config('gotract.lgas'))],
            'village'     => ['required', 'string', 'max:255'],

            // Step 2 — Identification & banking
            'nationalId' => [
                'required', 'digits:11',
                Rule::unique('gotract_applications', 'national_id'),
            ],
            'bvn'               => ['nullable', 'digits:11'],
            'bankAccountNumber' => ['nullable', 'digits:10'],
            'bankName'          => ['nullable', 'required_with:bankAccountNumber', 'string', 'max:255'],
            'hasDisability'     => ['required', 'boolean'],
            'disabilityType'    => ['nullable', 'required_if:hasDisability,true', Rule::in(config('gotract.disability_types'))],
            'disabilityOther'   => ['nullable', 'required_if:disabilityType,Other', 'string', 'max:255'],

            // Step 3 — Demographic & economic
            'maritalStatus'     => ['required', Rule::in(config('gotract.marital_statuses'))],
            'primaryOccupation' => ['required', 'string', 'max:255'],
            'cropsFarmed'       => ['nullable', 'string', 'max:255'],
            'householdSize'     => ['required', 'integer', 'min:1', 'max:100'],
            'dependents'        => ['nullable', 'integer', 'min:0', 'max:100'],
            'landArea'          => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'landOwnership'     => ['nullable', Rule::in(config('gotract.land_ownership'))],

            // Step 4 — Mechanization & financial
            'inCooperative'       => ['required', 'boolean'],
            'cooperativeName'     => ['nullable', 'required_if:inCooperative,true', 'string', 'max:255'],
            'priorMechExperience' => ['required', 'boolean'],
            'preferredServices'   => ['required', 'array', 'min:1'],
            'preferredServices.*' => [Rule::in(config('gotract.services'))],
            'currentlyEmployed'   => ['nullable', 'boolean'],
            'willingRepayment'    => ['required', 'boolean'],
            'accessToCredit'      => ['nullable', 'boolean'],

            // Step 5 — Training & consent
            'trainingAreas'   => ['required', 'array', 'min:1'],
            'trainingAreas.*' => [Rule::in(config('gotract.training_areas'))],
            'trainingOther'   => ['nullable', 'string', 'max:255'],
            'consent'         => ['required', 'accepted'],
            'signature'       => ['required', 'string', 'max:255'],

            'submittedAt' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'nationalId.unique'   => 'An application with this NIN already exists.',
            'consent.accepted'    => 'You must consent before submitting your application.',
            'preferredServices.min' => 'Select at least one mechanization service.',
            'trainingAreas.min'     => 'Select at least one training area.',
        ];
    }

    /**
     * Stop accepting applications once an LGA reaches its cap.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lga = $this->input('lga');
            $cap = (int) config('gotract.application_cap_per_lga');

            if ($lga && $cap > 0) {
                $received = GoTractApplication::where('lga', $lga)->count();
                if ($received >= $cap) {
                    $validator->errors()->add(
                        'lga',
                        "Applications for {$lga} have reached the maximum for this programme. Please contact the programme office."
                    );
                }
            }
        });
    }

    /**
     * Map the validated camelCase input onto the snake_case columns,
     * computing age from the date of birth server-side rather than trusting
     * the client, and collapsing optional/conditional fields to null.
     */
    public function mappedData(): array
    {
        $age = \Carbon\Carbon::parse($this->input('dateOfBirth'))->age;
        $hasDisability = $this->boolean('hasDisability');
        $inCooperative = $this->boolean('inCooperative');

        return [
            'full_name'   => $this->input('fullName'),
            'gender'      => $this->input('gender'),
            'date_of_birth' => $this->input('dateOfBirth'),
            'age'         => $age,
            'phone_number' => $this->input('phoneNumber'),
            'email'       => $this->input('email'),
            'state'       => $this->input('state', 'Gombe'),
            'lga'         => $this->input('lga'),
            'village'     => $this->input('village'),

            'national_id'         => $this->input('nationalId'),
            'bvn'                 => $this->input('bvn'),
            'bank_account_number' => $this->input('bankAccountNumber'),
            'bank_name'           => $this->input('bankName'),
            'has_disability'      => $hasDisability,
            'disability_type'     => $hasDisability ? $this->input('disabilityType') : null,
            'disability_other'    => $this->input('disabilityType') === 'Other' ? $this->input('disabilityOther') : null,

            'marital_status'    => $this->input('maritalStatus'),
            'primary_occupation' => $this->input('primaryOccupation'),
            'crops_farmed'      => $this->input('cropsFarmed'),
            'household_size'    => $this->input('householdSize'),
            'dependents'        => $this->input('dependents'),
            'land_area'         => $this->input('landArea'),
            'land_ownership'    => $this->input('landOwnership'),

            'in_cooperative'        => $inCooperative,
            'cooperative_name'      => $inCooperative ? $this->input('cooperativeName') : null,
            'prior_mech_experience' => $this->boolean('priorMechExperience'),
            'preferred_services'    => $this->input('preferredServices', []),
            'currently_employed'    => $this->boolean('currentlyEmployed'),
            'willing_repayment'     => $this->boolean('willingRepayment'),
            'access_to_credit'      => $this->boolean('accessToCredit'),

            'training_areas' => $this->input('trainingAreas', []),
            'training_other' => in_array('other', (array) $this->input('trainingAreas', []), true)
                ? $this->input('trainingOther')
                : null,
            'consent'   => $this->boolean('consent'),
            'signature' => $this->input('signature'),

            'ip_address'   => $this->ip(),
            'submitted_at' => $this->input('submittedAt') ?: now(),
        ];
    }
}