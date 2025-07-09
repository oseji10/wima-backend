<?php

namespace App\Imports;

use App\Models\JAMB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Validator;

class JambRecordsImport implements ToModel, WithHeadingRow
{
    protected $successfulCount = 0;
    protected $skippedCount = 0;

    public function model(array $row)
    {
        // Normalize header keys to handle case-sensitivity
        $data = array_change_key_case($row, CASE_LOWER);

        // Map Excel headers to database fields
        $mappedData = [
            'jambId' => $data['jambid'] ?? null,
            'firstName' => $data['firstname'] ?? null,
            'lastName' => $data['lastname'] ?? null,
            'otherNames' => $data['othernames'] ?? null,
            'gender' => $data['gender'] ?? null,
            'state' => $data['state'] ?? null,
            'aggregateScore' => $data['aggregatescore'] ?? null,
        ];

        // Check if a record with the given jambId already exists
        if (JAMB::where('jambId', $mappedData['jambId'])->exists()) {
            $this->skippedCount++;
            return null; // Skip the record if jambId exists
        }

        // Validate each row
        $validator = Validator::make($mappedData, [
            'jambId' => 'required',
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'otherNames' => 'nullable|string|max:255',
            'gender' => 'required|in:M,F,Other',
            'state' => 'required|string|max:100',
            'aggregateScore' => 'required|numeric|min:0|max:400',
        ]);

        if ($validator->fails()) {
            throw new \Exception('Invalid data in row: ' . json_encode($validator->errors()));
        }

        $this->successfulCount++;
        return new JAMB([
            'jambId' => $mappedData['jambId'],
            'firstName' => $mappedData['firstName'],
            'lastName' => $mappedData['lastName'],
            'otherNames' => $mappedData['otherNames'],
            'gender' => $mappedData['gender'],
            'state' => $mappedData['state'],
            'aggregateScore' => $mappedData['aggregateScore'],
        ]);
    }

    public function getSuccessfulCount(): int
    {
        return $this->successfulCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}