<?php

namespace App\Services\Documents;

use App\Models\Agent;
use App\Models\Business;
use App\Models\Complex;
use App\Models\ComplexExpense;
use App\Models\ComplexHistory;
use App\Models\Legal;
use App\Models\LegalFolder;
use App\Models\TenantManagement;
use App\Models\TenantPayment;
use App\Models\User;
use App\Services\Access\BusinessPermissionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The document centre: one list of the files Erty keeps for a business, gathered from where each one lives.
 *
 * Each source needs the right that opens it in its own section, so the centre never shows a file the person
 * could not reach there. Sources tied to a property go through the models' property scope, so a team member
 * limited to their properties sees only those files. Files open through the section's own download route, or
 * through DocumentCenterController@file for files that have none.
 */
class DocumentCentre
{
    /** key => label, what it is, icon, the right that lists it, and (optionally) the right that opens it. */
    public const SOURCES = [
        'tenancy' => ['label' => 'Tenancy files', 'about' => 'Uploaded when a tenancy was set up', 'icon' => 'fa-house-user', 'right' => 'tenant_management.view'],
        'receipt' => ['label' => 'Payment receipts', 'about' => 'Receipts attached to rent payments', 'icon' => 'fa-receipt', 'right' => 'tenant_management.payments'],
        'expense' => ['label' => 'Expense evidence', 'about' => 'Invoices and proof on property expenses', 'icon' => 'fa-file-invoice-dollar', 'right' => 'expense_management.view'],
        'history' => ['label' => 'Property history', 'about' => 'Title deeds, permits and photos on history records', 'icon' => 'fa-landmark', 'right' => 'listing_history.view'],
        'legal' => ['label' => 'Legal library', 'about' => 'Documents in the Legal section\'s folders', 'icon' => 'fa-balance-scale', 'right' => 'legal_documents.view', 'open' => 'legal_documents.download'],
        'agent' => ['label' => 'Agent CVs', 'about' => 'Uploaded on agent profiles', 'icon' => 'fa-id-badge', 'right' => 'agent_management.view'],
    ];

    public const TYPES = ['pdf' => 'PDF', 'image' => 'Image', 'word' => 'Word', 'sheet' => 'Spreadsheet', 'other' => 'Other'];

    public function __construct(private BusinessPermissionService $permissions)
    {
    }

    /** The sources this person may list, key => definition. */
    public function sourcesFor(User $user): array
    {
        return array_filter(self::SOURCES, fn ($s) => $this->has($user, $s['right']));
    }

    public function allows(User $user, string $source, bool $toOpen = false): bool
    {
        $definition = self::SOURCES[$source] ?? null;

        return $definition && $this->has($user, $toOpen ? ($definition['open'] ?? $definition['right']) : $definition['right']);
    }

    private function has(User $user, string $right): bool
    {
        [$group, $action] = explode('.', $right);

        return $this->permissions->can($user, $group, $action);
    }

    /**
     * Every document from the given sources, newest first. Each item: key, source, name, kind, type,
     * related (label), related_url, reference, property, complex_id, size (bytes or null), date, url (to open, or
     * null when the person may list but not open it), exists.
     */
    public function documents(User $user, array $sources): Collection
    {
        $items = collect();
        foreach ($sources as $source) {
            if ($this->allows($user, $source)) {
                $items = $items->merge($this->{$source}($user));
            }
        }

        return $items->sortByDesc(fn ($d) => $d['date']?->timestamp ?? 0)->values();
    }

    // ------------------------------------------------------------------ sources

    private function tenancy(User $user): Collection
    {
        $disk = Storage::disk('public');

        return TenantManagement::forBusiness()->whereNotNull('uploaded_documents')
            ->with(['tenantProfile', 'complex:id,title'])->get()
            ->flatMap(fn (TenantManagement $t) => collect(is_array($t->uploaded_documents) ? $t->uploaded_documents : [])
                ->map(function ($doc, $index) use ($t, $disk) {
                    $path = $doc['path'] ?? $doc['relative_path'] ?? null;
                    $exists = $path && $disk->exists($path);

                    return $this->item('tenancy', "{$t->id}:{$index}", [
                        'name' => $doc['original_name'] ?? $doc['stored_name'] ?? 'Document',
                        'kind' => $doc['file_type'] ?? 'Onboarding document',
                        'mime' => $doc['mime_type'] ?? null,
                        'related' => $t->tenantProfile?->display_name ?? ('Tenancy ' . $t->tenancy_ref),
                        'related_url' => tenancy_profile_url($t),
                        'reference' => $t->tenancy_ref,
                        'property' => $t->complex?->title ?? $t->complex_name,
                        'complex_id' => $t->complex_id,
                        'size' => isset($doc['size']) ? (int) $doc['size'] : null,
                        'date' => isset($doc['uploaded_at']) ? Carbon::parse($doc['uploaded_at']) : $t->created_at,
                        'exists' => $exists,
                        'url' => $exists ? route('document-center.file', ['source' => 'tenancy', 'id' => $t->id, 'index' => $index]) : null,
                    ]);
                }));
    }

    private function receipt(User $user): Collection
    {
        $disk = Storage::disk('public');

        return TenantPayment::query()->whereNotNull('receipt_path')
            ->whereHas('tenant', fn ($q) => $q->forBusiness())
            ->with(['tenant.tenantProfile', 'tenant.complex:id,title'])->get()
            ->map(function (TenantPayment $p) use ($disk) {
                $exists = $disk->exists($p->receipt_path);
                $t = $p->tenant;

                return $this->item('receipt', (string) $p->id, [
                    'name' => 'Receipt, ' . money($p->amount_paid ?? $p->total_amount, $t?->currency ?? null) . ($p->payment_date ? ' on ' . Carbon::parse($p->payment_date)->format('j M Y') : ''),
                    'kind' => 'Payment receipt' . ($p->transaction_reference ? ' · ' . $p->transaction_reference : ''),
                    'mime' => $this->mimeFromName($p->receipt_path),
                    'related' => $t?->tenantProfile?->display_name ?? ($t ? 'Tenancy ' . $t->tenancy_ref : 'Tenancy'),
                    'related_url' => $t ? tenancy_profile_url($t) : null,
                    'reference' => $t?->tenancy_ref,
                    'property' => $t?->complex?->title ?? $t?->complex_name,
                    'complex_id' => $t?->complex_id,
                    'size' => $exists ? $disk->size($p->receipt_path) : null,
                    'date' => $p->created_at,
                    'exists' => $exists,
                    'url' => $exists ? route('tenant_account.receipt', $p->id) : null,
                ]);
            });
    }

    private function expense(User $user): Collection
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('property_expense_evidence')) {
            return collect();
        }
        $expenses = ComplexExpense::query()->where('transaction_type', 'expense')
            ->whereIn('complex_id', Complex::forBusiness()->select('id'))
            ->get(['id', 'description', 'amount', 'currency', 'complex_id', 'expense_date'])->keyBy('id');
        if ($expenses->isEmpty()) {
            return collect();
        }
        $properties = Complex::whereIn('id', $expenses->pluck('complex_id')->unique())->pluck('title', 'id');
        $disk = Storage::disk('local');

        return DB::table('property_expense_evidence')->whereIn('expense_id', $expenses->keys())->orderByDesc('id')->get()
            ->map(function ($file) use ($expenses, $properties, $disk) {
                $expense = $expenses[$file->expense_id];
                $exists = $disk->exists($file->path);

                return $this->item('expense', (string) $file->id, [
                    'name' => $file->original_name,
                    'kind' => 'Expense evidence',
                    'mime' => $this->mimeFromName($file->original_name),
                    'related' => $expense->description ?: 'Expense #' . $expense->id,
                    'related_url' => can_open('/complex-expenses/' . $expense->id . '/workflow') ? route('business.expenses.workflow', $expense->id) : null,
                    'reference' => money($expense->amount, $expense->currency),
                    'property' => $properties[$expense->complex_id] ?? null,
                    'complex_id' => $expense->complex_id,
                    'size' => $exists ? $disk->size($file->path) : null,
                    'date' => $file->created_at ? Carbon::parse($file->created_at) : null,
                    'exists' => $exists,
                    'url' => $exists ? route('business.expenses.evidence.download', [$expense->id, $file->id]) : null,
                ]);
            });
    }

    private function history(User $user): Collection
    {
        $disk = Storage::disk('public');

        return ComplexHistory::query()->whereIn('complex_id', Complex::forBusiness()->select('id'))->whereNotNull('files')
            ->with(['complex:id,title', 'category'])->get()
            ->flatMap(fn (ComplexHistory $record) => collect(self::historyFiles($record))
                ->map(function ($file, $index) use ($record, $disk) {
                    $exists = $disk->exists($file['path']);

                    return $this->item('history', "{$record->id}:{$index}", [
                        'name' => $file['name'],
                        'kind' => $record->category?->name ?? 'Property history',
                        'mime' => $file['mime'] ?: $this->mimeFromName($file['name']),
                        'related' => ($record->category?->name ?? 'History record') . ' #' . $record->id,
                        'related_url' => can_open('/history/' . $record->id) ? route('history.show', $record->id) : null,
                        'reference' => null,
                        'property' => $record->complex?->title,
                        'complex_id' => $record->complex_id,
                        'size' => $file['size'] ?: null,
                        'date' => $file['uploaded_at'] ? Carbon::parse($file['uploaded_at']) : $record->created_at,
                        'exists' => $exists,
                        'url' => $exists ? route('document-center.file', ['source' => 'history', 'id' => $record->id, 'index' => $index]) : null,
                    ]);
                }));
    }

    private function legal(User $user): Collection
    {
        $business = Business::where('user_id', business_owner_id())->value('business_name');
        $folders = LegalFolder::forBusiness()->pluck('name', 'id');
        $types = DB::table('legal_types')->pluck('legal_type', 'id');
        $disk = Storage::disk('public');
        $canOpen = $this->allows($user, 'legal', true);

        return Legal::forBusiness()->orderByDesc('id')->get()->map(function (Legal $doc) use ($business, $folders, $types, $disk, $canOpen) {
            $folder = $folders[$doc->legal_folder_id] ?? null;
            $path = $business && $folder ? 'users/' . $business . '/legal/' . $business . '/' . $folder . '/' . $doc->file_name : null;
            $exists = $path && $disk->exists($path);

            return $this->item('legal', (string) $doc->id, [
                'name' => $doc->file_name,
                'kind' => $types[$doc->legal_type_id] ?? 'Legal document',
                'mime' => $this->mimeFromName($doc->file_name),
                'related' => $folder ? 'Folder: ' . $folder : 'Legal library',
                'related_url' => can_open('/legal') ? url('/legal') : null,
                'reference' => null,
                'property' => null,
                'complex_id' => null,
                'size' => $exists ? $disk->size($path) : null,
                'date' => $doc->created_at,
                'exists' => $exists,
                'url' => $exists && $canOpen ? url('/download/legal_document/' . rawurlencode($doc->file_name)) : null,
            ]);
        });
    }

    private function agent(User $user): Collection
    {
        $disk = Storage::disk('public');

        return Agent::where('created_by', business_owner_id())->whereNotNull('resume')->where('resume', '!=', '')->get()
            ->map(function (Agent $agent) use ($disk) {
                $exists = $disk->exists($agent->resume);

                return $this->item('agent', (string) $agent->id, [
                    'name' => 'CV, ' . (trim($agent->first_name . ' ' . $agent->last_name) ?: $agent->company_name) . '.' . strtolower(pathinfo($agent->resume, PATHINFO_EXTENSION) ?: 'pdf'),
                    'kind' => 'CV',
                    'mime' => $this->mimeFromName($agent->resume),
                    'related' => trim($agent->first_name . ' ' . $agent->last_name) ?: $agent->company_name,
                    'related_url' => agent_profile_url($agent),
                    'reference' => null,
                    'property' => null,
                    'complex_id' => null,
                    'size' => $exists ? $disk->size($agent->resume) : null,
                    'date' => $agent->updated_at ?? $agent->created_at,
                    'exists' => $exists,
                    'url' => $exists ? route('document-center.file', ['source' => 'agent', 'id' => $agent->id, 'index' => 0]) : null,
                ]);
            });
    }

    // ------------------------------------------------------------------ helpers

    /** A history record's files in a fixed order: path, name, size, mime, uploaded_at. */
    public static function historyFiles(ComplexHistory $record): array
    {
        $out = [];
        foreach ((array) ($record->files ?? []) as $group) {
            foreach ((array) $group as $file) {
                if (isset($file['path'], $file['original_name'])) {
                    $out[] = ['path' => $file['path'], 'name' => $file['original_name'], 'size' => (int) ($file['size'] ?? 0),
                        'mime' => $file['mime_type'] ?? '', 'uploaded_at' => $file['uploaded_at'] ?? null];
                }
            }
        }

        return $out;
    }

    private function item(string $source, string $id, array $fields): array
    {
        return ['key' => $source . ':' . $id, 'source' => $source, 'type' => $this->typeOf($fields['mime'] ?? null, $fields['name'])] + $fields;
    }

    private function typeOf(?string $mime, string $name): string
    {
        $mime = (string) ($mime ?: $this->mimeFromName($name));

        return match (true) {
            str_contains($mime, 'pdf') => 'pdf',
            str_starts_with($mime, 'image/') => 'image',
            str_contains($mime, 'word') || str_contains($mime, 'opendocument.text') => 'word',
            str_contains($mime, 'sheet') || str_contains($mime, 'excel') || str_contains($mime, 'csv') => 'sheet',
            default => 'other',
        };
    }

    private function mimeFromName(?string $name): string
    {
        return [
            'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv',
        ][strtolower(pathinfo((string) $name, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
