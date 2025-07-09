<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transactions;
use App\Models\Beneficiary;
use App\Models\TransactionProducts;
use App\Models\PendingTransactions;
use App\Models\Products;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;


class TransactionsController extends Controller
{
    public function index()
    {
        $transactions = Transactions::with('transaction_products.products', 'beneficiary_info', 'seller')
        ->orderBy('created_at', 'desc')
        ->get();
        return response()->json($transactions);
    }
    public function show($transactionId)
    {
        $transaction = Transactions::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($transaction);
    }

    
    
public function initiate(Request $request): JsonResponse
{
    // Validate the incoming request
    $validator = Validator::make($request->all(), [
        'beneficiaryId' => 'required|exists:beneficiaries,beneficiaryId',
        'products' => 'required|array|min:1',
        'products.*.productId' => 'required|exists:products,productId',
        'products.*.quantity' => 'required|integer|min:1',
        'paymentMethod' => 'required|in:outright,loan',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Check authentication early
        $user = auth()->user();
        if (!$user || !$user->staff) {
            throw new \Exception('Authenticated user or staff data not found');
        }

        // Calculate total cost and validate stock
        $products = $request->products;
        $totalCost = 0;
        $validatedProducts = [];

        foreach ($products as $product) {
            $productId = $product['productId'];
            $quantity = $product['quantity'];

            $productModel = Products::findOrFail($productId);
            $stock = Stock::where('productId', $productId)->firstOrFail();
            $availableStock = $stock->quantityReceived - ($stock->quantitySold ?? 0);

            if ($quantity > $availableStock) {
                throw new \Exception("Insufficient stock for product ID {$productId}. Available: {$availableStock}, Requested: {$quantity}");
            }

            $totalCost += $productModel->cost * $quantity;
            $validatedProducts[] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'cost' => $productModel->cost,
                'stock' => $stock,
            ];
        }

        // Generate a unique transaction ID
        $transactionId = Str::random(12);

        if ($request->paymentMethod === 'outright') {
            // $totalCostInKobo = $totalCost * 100; // Convert to kobo

              $moniepointResponse = Http::withOptions([
    'verify' => false,
])->withHeaders([
        'Authorization' => 'Bearer mptp_a72e62d6220b4c279f05f0d90c71f79b_cce5ff',
        'Cookie' => '__cf_bm=llJAllZZ4ww_EAgd7WsHAiW9Xhdt5tOKkWsvByK6X2c-1750629087-1.0.1.1-2zOUQHrb5PyiYLrXqoA6kiONrHhKIZ2z7ifHO.iSk1Ue539LjL8bhuUWeZ7RaafQfCvMnh9Ke08Ks7Kkt4k0T2H0uJb89.aTwZt52.qkpyM'
    ])->post('https://api.pos.moniepoint.com/v1/transactions', [
        'terminalSerial' => 'P260302358597',
        'amount' => $totalCost,
        'merchantReference' => $transactionId,
        'transactionType' => 'PURCHASE',
        'paymentMethod' => 'CARD_PURCHASE'
    ]);

            // if ($moniepointResponse->successful() && $moniepointResponse->json('status') === 'success') {
            //     \Log::info('Moniepoint payment successful', [
            //         'transactionId' => $transactionId,
            //         'totalCost' => $totalCost,
            //     ]);

            if ($moniepointResponse->status() === 202) {

   

    // return response()->json([
        // 'status' => 'success',
        // 'message' => 'Payment request accepted by Moniepoint.',
        // 'moniepointStatus' => $moniepointResponse->status(),
        // 'moniepointDescription' => 'Accepted'
    // ], 202);

                return DB::transaction(function () use ($transactionId, $request, $validatedProducts, $totalCost, $user) {
                    // Store in PendingTransactions
                    $pendingTransaction = PendingTransactions::create([
                        'transactionId' => $transactionId,
                        'beneficiaryId' => $request->beneficiaryId,
                        'paymentMethod' => $request->paymentMethod,
                        'products' => json_encode($validatedProducts),
                        'totalCost' => $totalCost,
                        'status' => 'completed',
                    ]);

                    // Create the transaction
                    $transaction = Transactions::create([
                        'transactionId' => $transactionId,
                        'beneficiary' => $pendingTransaction->beneficiaryId,
                        'paymentMethod' => $pendingTransaction->paymentMethod,
                        'lga' => $user->staff->lga,
                        'soldBy' => $user->id,
                        'status' => $pendingTransaction->status,
                    ]);

                    // Process products
                    $transactionProducts = [];
                    foreach ($validatedProducts as $product) {
                        $transactionProducts[] = [
                            'transactionId' => $transactionId,
                            'productId' => $product['productId'],
                            'quantitySold' => $product['quantity'],
                            'cost' => $product['cost'],
                        ];

                        // Update stock
                        $product['stock']->increment('quantitySold', $product['quantity']);
                    }

                    // Insert transaction products
                    TransactionProducts::insert($transactionProducts);

                    // Delete pending transaction
                    $pendingTransaction->delete();

                    // Fetch the transaction with related data
                    $transaction = Transactions::with(['beneficiary_info', 'transaction_products.products'])
                        ->where('transactionId', $transactionId)
                        ->firstOrFail();

                    // Format response
                    $response = [
                        'status' => 'success',
        'message' => 'Payment request accepted by Moniepoint.',
        'moniepointStatus' => 202,
        'moniepointDescription' => 'Accepted',
                        'id' => $transaction->id,
                        // 'beneficiary' => $transaction->beneficiary,
                        'beneficiary' => [
                            'firstName' => $transaction->beneficiary_info->firstName,
                            'lastName' => $transaction->beneficiary_info->lastName
                        ],
                        // 'beneficiary' => $transaction->beneficiary_info,
                        'transactionId' => $transaction->transactionId,
                        'lga' => $transaction->lga,
                        'soldBy' => $transaction->soldBy,
                        'paymentMethod' => $transaction->paymentMethod,
                        'status' => $transaction->status,
                        'created_at' => $transaction->created_at->toIso8601String(),
                        'updated_at' => $transaction->updated_at->toIso8601String(),
                        'transaction_products' => $transaction->transaction_products->map(function ($transactionProduct) {
                            return [
                                'id' => $transactionProduct->id,
                                'transactionId' => $transactionProduct->transactionId,
                                'productId' => $transactionProduct->productId,
                                'quantitySold' => $transactionProduct->quantitySold,
                                'cost' => $transactionProduct->cost,
                                'created_at' => $transactionProduct->created_at?->toIso8601String(),
                                'updated_at' => $transactionProduct->updated_at?->toIso8601String(),
                                'products' => [
                                    'productId' => $transactionProduct->products->productId,
                                    'productName' => $transactionProduct->products->productName ?? 'Unknown Product',
                                    'productType' => $transactionProduct->products->productType,
                                    'cost' => $transactionProduct->products->cost,
                                    'addedBy' => $transactionProduct->products->addedBy,
                                    'status' => $transactionProduct->products->status,
                                    'created_at' => $transactionProduct->products->created_at->toIso8601String(),
                                    'updated_at' => $transactionProduct->products->updated_at->toIso8601String(),
                                ],
                            ];
                        })->toArray(),
                    ];

                    return response()->json($response, 202);
                });
            } else {
                \Log::error('Moniepoint payment failed', [
                    'transactionId' => $transactionId,
                    'status' => $moniepointResponse->status(),
                    'body' => $moniepointResponse->body(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $moniepointResponse->json('error', 'Payment request failed'),
                ], 422);
            }
        }

        // Handle loan payment method (if applicable)
        throw new \Exception('Loan payment method not implemented');
    } catch (\Exception $e) {
        \Log::error('Transaction initiation failed', [
            'error' => $e->getMessage(),
            'transactionId' => $transactionId ?? null,
        ]);

        return response()->json([
            'message' => 'Failed to initiate transaction',
            'error' => $e->getMessage(),
        ], 500);
    }
}


 public function storeLoanTransactions(Request $request)
    {
        // Validating the incoming request data
        $validator = Validator::make($request->all(), [
            'beneficiaryId' => 'required|exists:beneficiaries,beneficiaryId',
            'paymentMethod' => 'required|in:loan',
            'products' => 'required|array|min:1',
            'products.*.productId' => 'required|exists:products,productId',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Start a database transaction
        return DB::transaction(function () use ($request) {
            // $users_info = 
            $beneficiary = Beneficiary::where('beneficiaryId', $request->beneficiaryId)->first();
            $totalCost = 0;

            // Calculate total cost
            foreach ($request->products as $productData) {
                $product = Products::where('productId', $productData['productId'])->first();
                if (!$product) {
                    throw new \Exception("Product with ID {$productData['productId']} not found");
                }
                $totalCost += $product->cost * $productData['quantity'];
            }

            // Verify loan limit
            $salary = 70000;
            // $salary = floatval($beneficiary->cadre_info->salary);
        //    return $salary = ($beneficiary->cadre_info->salary);
            $loanLimit = $beneficiary->beneficiaryType === 'State Civil Servant' 
                ? round($salary * 0.3333) 
                : floatval($beneficiary->billingSetting);

            // if ($totalCost > $loanLimit) {
            //     return response()->json([
            //         'message' => "Transaction exceeds loan limit of ₦{$loanLimit}",
            //     ], 422);
            // }

            // Create loan transaction
            $transaction = Transactions::create([
                'transactionId' => Str::random(),
                'beneficiary' => $beneficiary->id,
                'soldBy' => Auth::id(),
                'paymentMethod' => $request->paymentMethod,
                'status' => 'pending',
                'totalCost' => $totalCost,
            ]);

            // Create transaction products
            foreach ($request->products as $productData) {
                $product = Products::where('productId', $productData['productId'])->first();
                TransactionProducts::create([
                    'transactionId' => $transaction->transactionId,
                    'productId' => $productData['productId'],
                    'quantitySold' => $productData['quantity'],
                    'cost' => $product->cost,
                ]);
            }

            // Prepare response data
            $transaction->load('transaction_products.products', 'beneficiary_info', 'seller');

            return response()->json([
                'message' => 'Loan transaction created successfully',
                'data' => $transaction,
            ], 201);
        }, 5);
    }

    public function confirm(Request $request, string $transactionId): JsonResponse
    {
        try {
            // Find pending transaction
            $pendingTransaction = PendingTransactions::where('transactionId', $transactionId)->first();
            if (!$pendingTransaction) {
                return response()->json([
                    'message' => 'Pending transaction not found'
                ], 404);
            }

            // If already processed, return existing transaction
            $existingTransaction = Transactions::where('transactionId', $transactionId)->first();
            if ($existingTransaction) {
                return response()->json($existingTransaction, 200);
            }

            // For outright, ensure payment was completed
            if ($pendingTransaction->paymentMethod === 'outright' && $pendingTransaction->status !== 'completed') {
                return response()->json([
                    'message' => 'Payment not confirmed',
                    'status' => 'failed'
                ], 400);
            }

            // Begin a database transaction
            return DB::transaction(function () use ($pendingTransaction, $transactionId) {
                // Get authenticated user
                $user = auth()->user();
                if (!$user || !$user->staff) {
                    throw new \Exception('Authenticated user or staff data not found');
                }

                // Create the transaction
                $transaction = Transactions::create([
                    'transactionId' => $transactionId,
                    'beneficiary' => $pendingTransaction->beneficiaryId,
                    'paymentMethod' => $pendingTransaction->paymentMethod,
                    'lga' => $user->staff->lga,
                    'soldBy' => $user->id,
                    'status' => $pendingTransaction->status,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                // Process products
                $products = json_decode($pendingTransaction->products, true);
                $transactionProducts = [];

                foreach ($products as $product) {
                    $productId = $product['productId'];
                    $quantity = $product['quantity'];

                    // Fetch product details
                    $productModel = Products::findOrFail($productId);

                    // Check stock availability
                    $stock = Stock::where('productId', $productId)->firstOrFail();
                    $availableStock = $stock->quantityReceived - ($stock->quantitySold ?? 0);

                    if ($quantity > $availableStock) {
                        throw new \Exception("Insufficient stock for product ID {$productId}. Available: {$availableStock}, Requested: {$quantity}");
                    }

                    // Create transaction product entry
                    $transactionProducts[] = [
                        'transactionId' => $transactionId,
                        'productId' => $productId,
                        'quantitySold' => $quantity,
                        'cost' => $productModel->cost,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];

                    // Update stock
                    $stock->increment('quantitySold', $quantity);
                }

                // Insert transaction products
                TransactionProducts::insert($transactionProducts);

                // Delete pending transaction
                $pendingTransaction->delete();

                // Fetch the transaction with related data
                $transaction = Transactions::with(['beneficiary', 'transaction_products.products'])
                    ->where('transactionId', $transactionId)
                    ->firstOrFail();

                // Format response
                $response = [
                    'id' => $transaction->id,
                    'beneficiary' => $transaction->beneficiary,
                    'transactionId' => $transaction->transactionId,
                    'lga' => $transaction->lga,
                    'soldBy' => $transaction->soldBy,
                    'paymentMethod' => $transaction->paymentMethod,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at->toIso8601String(),
                    'updated_at' => $transaction->updated_at->toIso8601String(),
                    'transaction_products' => $transaction->transaction_products->map(function ($transactionProduct) {
                        return [
                            'id' => $transactionProduct->id,
                            'transactionId' => $transactionProduct->transactionId,
                            'productId' => $transactionProduct->productId,
                            'quantitySold' => (string) $transactionProduct->quantitySold,
                            'cost' => (string) $transactionProduct->cost,
                            'created_at' => $transactionProduct->created_at ? $transactionProduct->created_at->toIso8601String() : null,
                            'updated_at' => $transactionProduct->updated_at ? $transactionProduct->updated_at->toIso8601String() : null,
                            'products' => [
                                'productId' => $transactionProduct->products->productId,
                                'productName' => $transactionProduct->products->productName ?? 'Unknown Product',
                                'productType' => $transactionProduct->products->productType,
                                'cost' => (string) $transactionProduct->products->cost,
                                'addedBy' => $transactionProduct->products->addedBy,
                                'status' => $transactionProduct->products->status,
                                'created_at' => $transactionProduct->products->created_at->toIso8601String(),
                                'updated_at' => $transactionProduct->products->updated_at->toIso8601String(),
                            ]
                        ];
                    })->toArray(),
                ];

                return response()->json($response, 200);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to confirm transaction',
                'error' => $e->getMessage(),
                'status' => 'failed'
            ], 500);
        }
    }
    
    public function update(Request $request, $transactionId)
    {
        $transaction = Transaction::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $data = $request->all();
        $transaction->update($data);

        return response()->json([
            'message' => 'Transaction updated successfully',
            'transactionId' => $transaction->transactionId,
            'transactionName' => $transaction->transactionName], 201); // HTTP status code 201: Created

    }
    
    public function destroy($transactionId)
    {
        $transaction = Transaction::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $transaction->delete();
        return response()->json(['message' => 'Transaction deleted successfully']);
    }
    
}
