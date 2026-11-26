<?php

namespace App\Http\Controllers\Business\Crm;

use Carbon\Carbon;
use App\Models\Agent;
use Illuminate\Http\Request;
use App\Models\TenantManagement;
use App\Models\TenantLegalDocument;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class DocumentCenterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the Document Center index page.
     */
    public function index()
    {
        return view('pages.business.crm.document_management.index');
    }

    /**
     * Get all documents across all sources via AJAX (DataTable endpoint).
     */
    public function getDocumentsData(Request $request)
    {
        try {
            $userId = business_owner_id();
            $documents = $this->collectAllDocuments($userId);

            // Apply filters
            $documents = $this->applyFilters($documents, $request);

            // Sort
            $documents = $this->applySorting($documents, $request);

            $total = $documents->count();

            // Paginate
            $start = $request->input('start', 0);
            $length = $request->input('length', 25);
            $paginated = $documents->slice($start, $length)->values();

            return response()->json([
                'status' => 200,
                'data' => $paginated,
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching document center data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'error' => 'Failed to retrieve documents'
            ]);
        }
    }

    /**
     * Get document statistics for the dashboard cards.
     */
    public function getStats()
    {
        try {
            $stats = $this->getDocumentStats(business_owner_id());

            return response()->json([
                'status' => 200,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching document stats: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Failed to load stats'], 500);
        }
    }

    /**
     * Collect all documents using the same fetcher methods the DataTable uses.
     * Returns the merged collection (used for both stats and data consistency).
     */
    private function collectAllDocuments($userId)
    {
        $documents = collect();

        try {
            $documents = $documents->merge($this->getTenantOnboardingDocuments($userId));
        } catch (\Exception $e) {
            Log::warning('Document Center stats - Failed to load tenant onboarding docs: ' . $e->getMessage());
        }

        try {
            $documents = $documents->merge($this->getTenantLegalDocuments($userId));
        } catch (\Exception $e) {
            Log::warning('Document Center stats - Failed to load tenant legal docs: ' . $e->getMessage());
        }

        try {
            $documents = $documents->merge($this->getAgentDocuments($userId));
        } catch (\Exception $e) {
            Log::warning('Document Center stats - Failed to load agent docs: ' . $e->getMessage());
        }

        return $documents;
    }

    // =========================================================================
    // Document Source Fetchers
    // =========================================================================

    /**
     * Get documents uploaded during tenant onboarding (stored in TenantManagement.uploaded_documents JSON).
     */
    private function getTenantOnboardingDocuments($userId)
    {
        $tenancies = TenantManagement::forBusiness($userId)
            ->whereNotNull('uploaded_documents')
            ->with('tenantProfile')
            ->get();

        $docs = collect();

        foreach ($tenancies as $tenancy) {
            $uploadedDocs = $tenancy->uploaded_documents;
            if (!is_array($uploadedDocs) || empty($uploadedDocs)) continue;

            $tenantName = optional($tenancy->tenantProfile)->tenant_name 
                ?? optional($tenancy->tenantProfile)->business_name 
                ?? 'Tenant ' . ($tenancy->tenant_ref ?? $tenancy->id);

            foreach ($uploadedDocs as $doc) {
                $filePath = $doc['path'] ?? $doc['relative_path'] ?? null;
                $fileExists = $filePath ? Storage::disk('public')->exists($filePath) : false;

                $docs->push([
                    'id' => 'tenant_onboard_' . $tenancy->id . '_' . ($doc['stored_name'] ?? uniqid()),
                    'name' => $doc['original_name'] ?? $doc['stored_name'] ?? 'Unknown',
                    'source' => 'Tenant Onboarding',
                    'source_type' => 'tenant_onboarding',
                    'category' => $doc['file_type'] ?? $this->guessFileType($doc['mime_type'] ?? ''),
                    'related_to' => $tenantName,
                    'related_ref' => $tenancy->tenant_ref ?? null,
                    'file_size' => $doc['size'] ?? 0,
                    'file_size_formatted' => file_size($doc['size'] ?? 0),
                    'mime_type' => $doc['mime_type'] ?? 'application/octet-stream',
                    'uploaded_at' => isset($doc['uploaded_at']) ? Carbon::parse($doc['uploaded_at'])->format('Y-m-d H:i:s') : null,
                    'uploaded_at_formatted' => isset($doc['uploaded_at']) ? Carbon::parse($doc['uploaded_at'])->format('M j, Y') : 'N/A',
                    'exists' => $fileExists,
                    'url' => $fileExists && $filePath ? Storage::url($filePath) : null,
                    'path' => $filePath,
                ]);
            }
        }

        return $docs;
    }

    /**
     * Get legal documents linked to tenants via TenantLegalDocument junction table.
     */
    private function getTenantLegalDocuments($userId)
    {
        $legalDocs = TenantLegalDocument::join('legals', 'tenant_legal_documents.legal_document_id', '=', 'legals.id')
            ->join('tenant_management', 'tenant_legal_documents.tenant_management_id', '=', 'tenant_management.id')
            ->leftJoin('tenant_profiles', 'tenant_management.tenant_profile_id', '=', 'tenant_profiles.id')
            ->leftJoin('legal_types', 'legals.legal_type_id', '=', 'legal_types.id')
            ->leftJoin('legal_folders', 'legals.legal_folder_id', '=', 'legal_folders.id')
            ->where('legals.user_id', $userId)
            ->select(
                'legals.id as legal_id',
                'legals.file_name',
                'legals.created_at',
                'legal_types.legal_type as legal_type_name',
                'legal_folders.name as folder_name',
                'tenant_management.tenant_ref',
                'tenant_profiles.tenant_name',
                'tenant_profiles.business_name'
            )
            ->get();

        return $legalDocs->map(function ($doc) {
            $tenantName = $doc->tenant_name ?? $doc->business_name ?? 'Tenant ' . ($doc->tenant_ref ?? 'Unknown');

            return [
                'id' => 'tenant_legal_' . $doc->legal_id . '_' . $doc->tenant_ref,
                'name' => $doc->file_name ?? 'Legal Document',
                'source' => 'Tenant Legal Document',
                'source_type' => 'tenant_legal',
                'category' => $doc->legal_type_name ?? 'Legal',
                'related_to' => $tenantName,
                'related_ref' => $doc->tenant_ref,
                'file_size' => 0,
                'file_size_formatted' => 'N/A',
                'mime_type' => $this->guessMimeType($doc->file_name ?? ''),
                'uploaded_at' => $doc->created_at ? Carbon::parse($doc->created_at)->format('Y-m-d H:i:s') : null,
                'uploaded_at_formatted' => $doc->created_at ? Carbon::parse($doc->created_at)->format('M j, Y') : 'N/A',
                'exists' => true,
                'url' => null, // Legal docs are referenced by ID, not direct path
                'path' => null,
                'folder' => $doc->folder_name,
            ];
        });
    }

    /**
     * Get agent documents (resume, certifications, profile photo).
     */
    private function getAgentDocuments($userId)
    {
        $agents = Agent::where('created_by', $userId)->get();

        $docs = collect();

        foreach ($agents as $agent) {
            $agentName = trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? ''));
            if (empty($agentName)) $agentName = $agent->email ?? 'Agent #' . $agent->id;

            // Resume
            if (!empty($agent->resume)) {
                $fileExists = Storage::disk('public')->exists($agent->resume);
                $docs->push([
                    'id' => 'agent_resume_' . $agent->id,
                    'name' => 'Resume - ' . basename($agent->resume),
                    'source' => 'Agent',
                    'source_type' => 'agent',
                    'category' => 'Resume / CV',
                    'related_to' => $agentName,
                    'related_ref' => 'AGT-' . str_pad($agent->id, 6, '0', STR_PAD_LEFT),
                    'file_size' => $fileExists ? Storage::disk('public')->size($agent->resume) : 0,
                    'file_size_formatted' => $fileExists ? file_size(Storage::disk('public')->size($agent->resume)) : 'N/A',
                    'mime_type' => $fileExists ? Storage::disk('public')->mimeType($agent->resume) : $this->guessMimeType($agent->resume),
                    'uploaded_at' => $agent->created_at ? $agent->created_at->format('Y-m-d H:i:s') : null,
                    'uploaded_at_formatted' => $agent->created_at ? $agent->created_at->format('M j, Y') : 'N/A',
                    'exists' => $fileExists,
                    'url' => $fileExists ? Storage::url($agent->resume) : null,
                    'path' => $agent->resume,
                ]);
            }

            // Certifications
            $certifications = $agent->certifications;
            if (!empty($certifications) && is_array($certifications)) {
                foreach ($certifications as $index => $certPath) {
                    if (empty($certPath)) continue;

                    // Handle both string paths and associative arrays
                    $path = is_array($certPath) ? ($certPath['path'] ?? $certPath['file'] ?? '') : $certPath;
                    $certName = is_array($certPath) ? ($certPath['name'] ?? basename($path)) : basename($path);
                    
                    if (empty($path)) continue;

                    $fileExists = Storage::disk('public')->exists($path);
                    $docs->push([
                        'id' => 'agent_cert_' . $agent->id . '_' . $index,
                        'name' => 'Certification - ' . $certName,
                        'source' => 'Agent',
                        'source_type' => 'agent',
                        'category' => 'Certification',
                        'related_to' => $agentName,
                        'related_ref' => 'AGT-' . str_pad($agent->id, 6, '0', STR_PAD_LEFT),
                        'file_size' => $fileExists ? Storage::disk('public')->size($path) : 0,
                        'file_size_formatted' => $fileExists ? file_size(Storage::disk('public')->size($path)) : 'N/A',
                        'mime_type' => $fileExists ? Storage::disk('public')->mimeType($path) : $this->guessMimeType($path),
                        'uploaded_at' => $agent->created_at ? $agent->created_at->format('Y-m-d H:i:s') : null,
                        'uploaded_at_formatted' => $agent->created_at ? $agent->created_at->format('M j, Y') : 'N/A',
                        'exists' => $fileExists,
                        'url' => $fileExists ? Storage::url($path) : null,
                        'path' => $path,
                    ]);
                }
            }

            // Profile Photo
            if (!empty($agent->profile_photo)) {
                $fileExists = Storage::disk('public')->exists($agent->profile_photo);
                $docs->push([
                    'id' => 'agent_photo_' . $agent->id,
                    'name' => 'Profile Photo - ' . basename($agent->profile_photo),
                    'source' => 'Agent',
                    'source_type' => 'agent',
                    'category' => 'Profile Photo',
                    'related_to' => $agentName,
                    'related_ref' => 'AGT-' . str_pad($agent->id, 6, '0', STR_PAD_LEFT),
                    'file_size' => $fileExists ? Storage::disk('public')->size($agent->profile_photo) : 0,
                    'file_size_formatted' => $fileExists ? file_size(Storage::disk('public')->size($agent->profile_photo)) : 'N/A',
                    'mime_type' => $fileExists ? Storage::disk('public')->mimeType($agent->profile_photo) : $this->guessMimeType($agent->profile_photo),
                    'uploaded_at' => $agent->created_at ? $agent->created_at->format('Y-m-d H:i:s') : null,
                    'uploaded_at_formatted' => $agent->created_at ? $agent->created_at->format('M j, Y') : 'N/A',
                    'exists' => $fileExists,
                    'url' => $fileExists ? Storage::url($agent->profile_photo) : null,
                    'path' => $agent->profile_photo,
                ]);
            }
        }

        return $docs;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Compute overall document stats using the same fetcher methods as the DataTable.
     * This ensures stat counts always match what the table displays.
     */
    private function getDocumentStats($userId)
    {
        $documents = $this->collectAllDocuments($userId);

        $tenantOnboardingCount = $documents->where('source_type', 'tenant_onboarding')->count();
        $tenantLegalCount = $documents->where('source_type', 'tenant_legal')->count();
        $agentDocCount = $documents->where('source_type', 'agent')->count();

        return [
            'total' => $documents->count(),
            'tenant_onboarding' => $tenantOnboardingCount,
            'tenant_legal' => $tenantLegalCount,
            'agent' => $agentDocCount,
        ];
    }

    /**
     * Apply search/filter parameters to the documents collection.
     */
    private function applyFilters($documents, Request $request)
    {
        // Source type filter
        if ($request->filled('source_type') && $request->source_type !== 'all') {
            $documents = $documents->where('source_type', $request->source_type);
        }

        // Category filter
        if ($request->filled('category') && $request->category !== 'all') {
            $documents = $documents->where('category', $request->category);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $documents = $documents->filter(function ($doc) use ($dateFrom) {
                return $doc['uploaded_at'] && Carbon::parse($doc['uploaded_at'])->gte($dateFrom);
            });
        }
        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $documents = $documents->filter(function ($doc) use ($dateTo) {
                return $doc['uploaded_at'] && Carbon::parse($doc['uploaded_at'])->lte($dateTo);
            });
        }

        // Search filter
        if ($request->filled('search') && !empty($request->input('search')['value'])) {
            $searchTerm = strtolower($request->input('search')['value']);
            $documents = $documents->filter(function ($doc) use ($searchTerm) {
                return str_contains(strtolower($doc['name'] ?? ''), $searchTerm)
                    || str_contains(strtolower($doc['source'] ?? ''), $searchTerm)
                    || str_contains(strtolower($doc['related_to'] ?? ''), $searchTerm)
                    || str_contains(strtolower($doc['category'] ?? ''), $searchTerm)
                    || str_contains(strtolower($doc['related_ref'] ?? ''), $searchTerm);
            });
        }

        return $documents->values();
    }

    /**
     * Apply sorting to the documents collection.
     */
    private function applySorting($documents, Request $request)
    {
        $sortField = 'uploaded_at';
        $sortDir = 'desc';

        if ($request->has('order') && is_array($request->order)) {
            $columnIndex = $request->order[0]['column'] ?? 0;
            $sortDir = $request->order[0]['dir'] ?? 'desc';

            $columnMap = [
                0 => 'name',
                1 => 'source',
                2 => 'category',
                3 => 'related_to',
                4 => 'file_size',
                5 => 'uploaded_at',
            ];

            $sortField = $columnMap[$columnIndex] ?? 'uploaded_at';
        }

        if ($sortField === 'file_size') {
            $sortField = 'file_size'; // sort by raw bytes
        }

        return $sortDir === 'asc'
            ? $documents->sortBy($sortField)->values()
            : $documents->sortByDesc($sortField)->values();
    }

    /**
     * Guess file type from MIME type.
     */
    private function guessFileType($mimeType)
    {
        if (str_contains($mimeType, 'pdf')) return 'PDF Document';
        if (str_contains($mimeType, 'image')) return 'Image';
        if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) return 'Word Document';
        if (str_contains($mimeType, 'spreadsheet') || str_contains($mimeType, 'excel')) return 'Spreadsheet';
        if (str_contains($mimeType, 'text')) return 'Text File';
        return 'Document';
    }

    /**
     * Guess MIME type from file extension.
     */
    private function guessMimeType($filename)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
