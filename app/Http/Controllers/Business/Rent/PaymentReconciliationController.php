<?php

namespace App\Http\Controllers\Business\Rent;

use App\Http\Controllers\Controller;
use App\Models\TenantPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentReconciliationController extends Controller
{
    /**
     * Reconciliation page — stats only; panels load via AJAX.
     */
    public function index()
    {
        $userId = business_owner_id();

        $unmatchedPaymentsCount = TenantPayment::whereNull('receipt_path')->where('entry_type', 'payment')->where('payment_status', '!=', TenantPayment::REVERSED)
            ->whereHas('tenant', fn($q) => $q->where('managed_by', $userId))
            ->count();

        // Unmatched receipts are not tied to a property yet: only for people who work with every property.
        $stagedReceiptsCount = auth()->user()->limitedToAssignedProperties() ? 0 : DB::table('payment_receipt_staging')
            ->where('user_id', $userId)
            ->where('matched', false)
            ->count();

        $recentMatchesCount = DB::table('payment_receipt_staging as s')
            ->join('tenant_payments as p', 'p.id', '=', 's.matched_payment_id')
            ->join('tenant_management as tm', 'tm.id', '=', 'p.tenant_id')
            ->where('s.user_id', $userId)
            ->tap(fn ($q) => app(\App\Services\Access\PropertyAccess::class)->constrain($q, 'tm.complex_id', ['residential' => 'tm.residential_unit_id', 'commercial' => 'tm.commercial_unit_id']))
            ->where('s.matched', true)
            ->where('s.matched_at', '>=', now()->subDays(30))
            ->count();

        return view('pages.business.tenant.payment-reconciliation', compact(
            'unmatchedPaymentsCount',
            'stagedReceiptsCount',
            'recentMatchesCount'
        ) + ['filesHidden' => auth()->user()->limitedToAssignedProperties()]);
    }

    /**
     * AJAX: payments that have no proof of payment yet, newest first, 25 per page.
     */
    public function getUnmatchedPayments(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'tenant_search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = TenantPayment::whereNull('receipt_path')->where('entry_type', 'payment')->where('payment_status', '!=', TenantPayment::REVERSED)
            ->whereHas('tenant', fn ($q) => $q->where('managed_by', business_owner_id()))
            ->with(['tenant.tenantProfile', 'invoice']);

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }
        if ($request->filled('tenant_search')) {
            $term = '%' . mb_strtolower(trim($request->tenant_search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('tenant.tenantProfile', fn ($p) => $p
                        ->whereRaw('LOWER(tenant_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(business_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(tenant_email) LIKE ?', [$term]))
                  ->orWhereHas('tenant', fn ($t) => $t->whereRaw('LOWER(tenancy_ref) LIKE ?', [$term]))
                  ->orWhereRaw('LOWER(transaction_reference) LIKE ?', [$term]);
            });
        }

        $page = $query->orderByDesc('payment_date')->orderByDesc('id')->paginate(25, ['*'], 'page', $request->integer('page', 1));

        return response()->json([
            'data' => collect($page->items())->map(function (TenantPayment $p) {
                $profile = $p->tenant?->tenantProfile;
                return [
                    'id' => $p->id,
                    'payment_date' => $p->payment_date?->format('d M Y'),
                    'tenant_name' => $profile?->tenant_name ?: ($profile?->business_name ?: 'Unknown tenant'),
                    'tenancy_ref' => $p->tenant?->tenancy_ref ?? '—',
                    'invoice_number' => $p->invoice?->invoice_number ?? $p->invoice?->combined_invoice_number,
                    'amount' => money($p->amount_paid, $p->tenant?->currency),
                    'payment_method' => ucfirst($p->payment_method_label),
                    'transaction_reference' => $p->transaction_reference,
                ];
            }),
            'meta' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }

    /**
     * AJAX — Staged receipts not yet matched.
     */
    public function getStagedReceipts()
    {
        $receipts = DB::table('payment_receipt_staging')
            ->where('user_id', business_owner_id())
            ->when(auth()->user()->limitedToAssignedProperties(), fn ($q) => $q->whereRaw('1 = 0'))
            ->where('matched', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($r) {
                return [
                    'id'                => $r->id,
                    'original_filename' => $r->original_filename,
                    'file_size'         => file_size($r->file_size),
                    'mime_type'         => $r->mime_type,
                    'uploaded_at'       => \Carbon\Carbon::parse($r->created_at)->format('d M Y H:i'),
                    'is_image'          => str_starts_with($r->mime_type, 'image/'),
                    'url'               => asset('storage/' . $r->file_path),
                ];
            });

        return response()->json(['data' => $receipts]);
    }

    /**
     * Upload a receipt file to the staging area.
     */
    public function uploadReceipt(Request $request)
    {
        $request->validate([
            'receipt' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        $userId    = business_owner_id();
        $business  = auth()->user()->business->business_name;
        $file      = $request->file('receipt');
        $path      = 'users/' . $business . '/reconciliation/receipts';
        $filename  = 'staged_' . now()->format('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        $filePath = $file->storeAs($path, $filename, 'public');

        $id = DB::table('payment_receipt_staging')->insertGetId([
            'user_id'           => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'file_path'         => $filePath,
            'file_size'         => $file->getSize(),
            'mime_type'         => $file->getMimeType(),
            'matched'           => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return response()->json([
            'id'                => $id,
            'original_filename' => $file->getClientOriginalName(),
            'file_size_kb'      => round($file->getSize() / 1024, 1),
            'mime_type'         => $file->getMimeType(),
            'uploaded_at'       => now()->format('d M Y H:i'),
            'is_image'          => str_starts_with($file->getMimeType(), 'image/'),
            'url'               => asset('storage/' . $filePath),
        ]);
    }

    /**
     * Link a staged receipt to a payment.
     */
    public function matchReceiptToPayment(Request $request)
    {
        $request->validate([
            'staged_receipt_id' => 'required|integer',
            'payment_id'        => 'required|integer',
        ]);

        $userId = business_owner_id();

        $staged = DB::table('payment_receipt_staging')
            ->where('id', $request->staged_receipt_id)
            ->where('user_id', $userId)
            ->where('matched', false)
            ->first();

        if (!$staged) {
            return response()->json(['message' => 'Staged receipt not found or already matched.'], 404);
        }

        $payment = TenantPayment::whereNull('receipt_path')->where('entry_type', 'payment')->where('payment_status', '!=', TenantPayment::REVERSED)
            ->whereHas('tenant', fn($q) => $q->where('managed_by', $userId))
            ->find($request->payment_id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found or already has a receipt.'], 404);
        }

        return DB::transaction(function () use ($staged, $payment, $userId) {
            // Move file from staging path to tenant-specific path
            $business    = auth()->user()->business->business_name;
            $tenantRef   = $payment->tenant?->tenancy_ref ?? 'unknown';
            $invoiceRef  = $payment->invoice?->invoice_number ?? $payment->id;
            $ext         = pathinfo($staged->file_path, PATHINFO_EXTENSION);
            $newFilename = 'receipt_' . $invoiceRef . '_' . now()->format('Y-m-d_H-i-s') . '.' . $ext;
            $destPath    = 'users/' . $business . '/tenants/' . $tenantRef . '/payments/receipts/' . $newFilename;

            Storage::disk('public')->move($staged->file_path, $destPath);

            // Update payment
            $payment->update(['receipt_path' => $destPath]);

            // Mark staging row as matched
            DB::table('payment_receipt_staging')
                ->where('id', $staged->id)
                ->update([
                    'matched'            => true,
                    'matched_payment_id' => $payment->id,
                    'matched_at'         => now(),
                    'updated_at'         => now(),
                ]);

            Log::info('Receipt matched to payment', [
                'staged_id'  => $staged->id,
                'payment_id' => $payment->id,
                'dest_path'  => $destPath,
                'user_id'    => $userId,
            ]);

            return response()->json(['message' => 'Receipt matched successfully.']);
        });
    }

    /**
     * Unlink a receipt from a payment, returning the file to staged state.
     */
    public function unmatch($paymentId)
    {
        $userId  = business_owner_id();
        $payment = TenantPayment::whereNotNull('receipt_path')
            ->whereHas('tenant', fn($q) => $q->where('managed_by', $userId))
            ->findOrFail($paymentId);

        $staged = DB::table('payment_receipt_staging')
            ->where('matched_payment_id', $paymentId)
            ->where('user_id', $userId)
            ->first();

        return DB::transaction(function () use ($payment, $staged, $userId) {
            $business = auth()->user()->business->business_name;

            if ($staged) {
                // Move file back to staging area
                $ext         = pathinfo($payment->receipt_path, PATHINFO_EXTENSION);
                $filename    = 'staged_restored_' . now()->format('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $ext;
                $stagingPath = 'users/' . $business . '/reconciliation/receipts/' . $filename;

                if (Storage::disk('public')->exists($payment->receipt_path)) {
                    Storage::disk('public')->move($payment->receipt_path, $stagingPath);
                }

                DB::table('payment_receipt_staging')
                    ->where('id', $staged->id)
                    ->update([
                        'file_path'          => $stagingPath,
                        'matched'            => false,
                        'matched_payment_id' => null,
                        'matched_at'         => null,
                        'updated_at'         => now(),
                    ]);
            }

            $payment->update(['receipt_path' => null]);

            return response()->json(['message' => 'Receipt unmatched successfully.']);
        });
    }

    /**
     * Delete a staged receipt that has not yet been matched.
     */
    public function deleteStagedReceipt($id)
    {
        $staged = DB::table('payment_receipt_staging')
            ->where('id', $id)
            ->where('user_id', business_owner_id())
            ->where('matched', false)
            ->first();

        if (!$staged) {
            return response()->json(['message' => 'Receipt not found or already matched.'], 404);
        }

        Storage::disk('public')->delete($staged->file_path);

        DB::table('payment_receipt_staging')->where('id', $id)->delete();

        return response()->json(['message' => 'Receipt deleted.']);
    }

    /**
     * AJAX — Recently matched receipts (last 30 days).
     */
    public function getRecentMatches()
    {
        $userId = business_owner_id();

        $rows = DB::table('payment_receipt_staging as s')
            ->join('tenant_payments as p', 'p.id', '=', 's.matched_payment_id')
            ->join('tenant_management as tm', 'tm.id', '=', 'p.tenant_id')
            ->where('s.user_id', $userId)
            ->tap(fn ($q) => app(\App\Services\Access\PropertyAccess::class)->constrain($q, 'tm.complex_id', ['residential' => 'tm.residential_unit_id', 'commercial' => 'tm.commercial_unit_id']))
            ->where('s.matched', true)
            ->where('s.matched_at', '>=', now()->subDays(30))
            ->orderByDesc('s.matched_at')
            ->select(
                's.id as staged_id',
                's.original_filename',
                's.matched_at',
                'p.id as payment_id',
                'p.payment_date',
                'p.amount_paid',
                'p.payment_method',
                'p.transaction_reference',
                'tm.tenancy_ref',
                'tm.currency'
            )
            ->get()
            ->map(function ($r) {
                return [
                    'staged_id'             => $r->staged_id,
                    'original_filename'     => $r->original_filename,
                    'matched_at'            => \Carbon\Carbon::parse($r->matched_at)->format('d M Y H:i'),
                    'payment_id'            => $r->payment_id,
                    'payment_date'          => \Carbon\Carbon::parse($r->payment_date)->format('d M Y'),
                    'amount'                => money($r->amount_paid, $r->currency),
                    'payment_method'        => ucfirst(str_replace('_', ' ', (string) $r->payment_method)),
                    'transaction_reference' => $r->transaction_reference ?? '—',
                    'tenancy_ref'           => $r->tenancy_ref,
                ];
            });

        return response()->json(['data' => $rows]);
    }
}
