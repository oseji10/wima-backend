<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JAMB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\JambRecordsImport;
use Illuminate\Http\JsonResponse;
class JAMBController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $jambs = JAMB::orderBy('id', 'desc')->paginate($perPage);
        return response()->json($jambs);
    }

    public function destroy($jambId)
    {
        $jamb = JAMB::where('jambId', $jambId)->first();
        if ($jamb) {
            $jamb->delete();
            return response()->json(['success' => true, 'message' => 'Record deleted successfully']);
        }
        return response()->json(['success' => false, 'message' => 'Record not found'], 404);
    }


    public function search(Request $request)
    {
        $query = $request->query('query');
        $perPage = $request->query('per_page', 10);

        $jambs = JAMB::where('jambId', 'LIKE', "%{$query}%")
            ->orWhere('firstName', 'LIKE', "%{$query}%")
            ->orWhere('lastName', 'LIKE', "%{$query}%")
            ->orWhere('otherNames', 'LIKE', "%{$query}%")
            ->paginate($perPage);

        return response()->json($jambs);
    }
    
    public function upload(Request $request): JsonResponse
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xls,xlsx|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Process the Excel file using the JambRecordsImport class
            // Excel::import(new JambRecordsImport, $request->file('file'));
            $import = new JambRecordsImport();
            Excel::import($import, $request->file('file'));
             // Get the counts
            $successfulCount = $import->getSuccessfulCount();
            $skippedCount = $import->getSkippedCount();
            return response()->json([
                'success' => true,
                'message' => 'JAMB records uploaded successfully',
                'data' => [
                    'successful' => $successfulCount,
                    'skipped' => $skippedCount
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process the Excel file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     public function store(Request $request)
    {
       $validated = $request->validate([
            'jambId' => 'required|string|max:255',
        ]);

        $jambRecords = JAMB::create($validated);
        return response()->json($jambRecords, 201); // HTTP status code 201: Created

    }

   public function edit(Request $request, $cancerId)
{
    $validated = $request->validate([
        'cancerName' => 'required|string|max:255',
    ]);

    $cancer = Cancer::where('cancerId', $cancerId)->first();
    if (!$cancer) {
        return response()->json(['message' => 'Cancer type not found'], 404);
    }

    $cancer->update($validated);
    
    return response()->json([
        'cancerId' => $cancer->cancerId,
        'cancerName' => $cancer->cancerName
    ], 200);
}

    // public function destroy($cancerId)
    // {
    //     $cancer = Cancer::where('cancerId', $cancerId)->first();
    //     if (!$cancer) {
    //         return response()->json(['message' => 'Cancer type not found'], 404);
    //     }

    //     $cancer->delete();
    //     return response()->json(['message' => 'Cancer type deleted successfully'], 200);
    // }
   
    public function verifyJAMB(Request $request)
    {
        $jambRecord = JAMB::where('jambId', $request->jambId)->first();
        if (!$jambRecord) {
            return response()->json(['message' => 'JAMB record not found'], 404);
        }
        return response()->json($jambRecord);
    }
   
}
