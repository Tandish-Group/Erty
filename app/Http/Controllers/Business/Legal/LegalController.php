<?php

namespace App\Http\Controllers\Business\Legal;

use ZipArchive;
use App\Models\Legal;
use App\Models\Business;
use App\Models\LegalType;
use App\Models\LegalFolder;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use App\Http\Controllers\Controller;
use App\Services\StorageUsageService;

/**
 * Business legal document centre.
 *
 * Every file lives on the public disk at:
 *   users/{business}/legal/{business}/{folder}/{file}
 * All actions are scoped to the owning business (sub-accounts act for their main account).
 */
class LegalController extends Controller
{

    private function businessName(?int $ownerId = null): string
    {
        $name = Business::where('user_id', $ownerId ?? business_owner_id())->value('business_name');
        abort_unless($name, 404, 'Business account not found.');
        return $name;
    }

    private function disk(): Filesystem
    {
        return Storage::disk('public');
    }

    private function basePath(?int $ownerId = null): string
    {
        $business = $this->businessName($ownerId);
        return 'users/' . $business . '/legal/' . $business;
    }

    private function folderPath(LegalFolder $folder, ?int $ownerId = null): string
    {
        return $this->basePath($ownerId) . '/' . $folder->name;
    }

    private function folder(int $id): LegalFolder
    {
        return LegalFolder::forBusiness()->findOrFail($id);
    }

    private function failed(string $message, int $status = 422)
    {
        return response()->json(['status' => $status, 'message' => $message], $status);
    }

    private function validFolderName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '' || mb_strlen($name) > 100 || preg_match('#[/\\\\]|\.\.#', $name)) {
            return null;
        }
        return $name;
    }

    public function index()
    {
        $ownerId = business_owner_id();
        $types = LegalType::orderBy('legal_type')->get(['id', 'legal_type']);
        $folders = LegalFolder::forBusiness($ownerId)->orderBy('name')->get(['id', 'name', 'slug']);
        $typeNames = $types->pluck('legal_type', 'id');
        $folderById = $folders->keyBy('id');

        $documents = Legal::forBusiness($ownerId)->orderByDesc('created_at')->get()->map(function (Legal $file) use ($folderById, $typeNames, $ownerId) {
            $folder = $folderById->get($file->legal_folder_id);
            $path = $folder ? $this->folderPath($folder, $ownerId) . '/' . $file->file_name : null;
            $exists = $path && $this->disk()->exists($path);
            return [
                'id' => $file->id,
                'name' => $file->file_name,
                'extension' => strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION)),
                'type' => $typeNames[$file->legal_type_id] ?? 'Document',
                'folder_id' => $folder?->id,
                'folder' => $folder?->name ?? 'No folder',
                'bytes' => $exists ? $this->disk()->size($path) : 0,
                'exists' => $exists,
                'added' => $file->created_at,
            ];
        });

        $folders->each(fn ($folder) => $folder->setAttribute('document_count', $documents->where('folder_id', $folder->id)->count()));
        $totalBytes = $documents->sum('bytes');

        return view('pages.business.legal.index', compact('types', 'folders', 'documents', 'totalBytes'));
    }

    /** Create a folder. */
    public function store(Request $request)
    {
        $name = $this->validFolderName($request->value);
        if (!$name) {
            return $this->failed('Enter a folder name of up to 100 characters without slashes.');
        }

        $ownerId = business_owner_id();
        if (LegalFolder::forBusiness($ownerId)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            return $this->failed('A folder with this name already exists.');
        }

        $folder = LegalFolder::create(['name' => $name, 'slug' => $this->uniqueSlug($name, $ownerId), 'user_id' => $ownerId]);
        $this->disk()->makeDirectory($this->folderPath($folder));

        return response()->json(['status' => 200, 'id' => $folder->id]);
    }

    public function legal_document_upload(Request $request)
    {
        $values = is_string($request->values) ? json_decode($request->values, true) : $request->values;
        $folderId = (int) ($values['folder'] ?? 0);
        $typeId = (int) ($values['listing_type'] ?? 0);

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->failed('Choose a file to upload.');
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'], true) || $file->getSize() > 20 * 1024 * 1024) {
            return $this->failed('Upload a PDF, Word, Excel, image or text file of up to 20 MB.');
        }
        if (!LegalType::whereKey($typeId)->exists()) {
            return $this->failed('Choose a document type.');
        }
        $folder = LegalFolder::forBusiness()->find($folderId);
        if (!$folder) {
            return $this->failed('Choose one of your folders.');
        }

        $directory = $this->folderPath($folder);
        $fileName = $this->uniqueFileName($directory, $file->getClientOriginalName());
        $this->disk()->putFileAs($directory, $file, $fileName);

        try {
            Legal::create([
                'file_name' => $fileName,
                'legal_type_id' => $typeId,
                'legal_folder_id' => $folder->id,
                'user_id' => business_owner_id(),
            ]);
        } catch (\Throwable $e) {
            $this->disk()->delete($directory . '/' . $fileName);
            throw $e;
        }
        app(StorageUsageService::class)->incrementUsage($file->getSize(), business_owner_id());

        return response()->json(['status' => 200, 'file_name' => $fileName]);
    }

    public function legal_document_move(Request $request)
    {
        $values = $request->values ?? [];
        $ownerId = business_owner_id();
        $file = Legal::forBusiness($ownerId)->find((int) ($values['file_id'] ?? 0));
        $target = LegalFolder::forBusiness($ownerId)->find((int) ($values['move_to_folder'] ?? 0));
        if (!$file || !$target) {
            return $this->failed('Choose one of your documents and a destination folder.');
        }
        if ((int) $file->legal_folder_id === (int) $target->id) {
            return response()->json(['status' => 200]);
        }

        $source = LegalFolder::forBusiness($ownerId)->find($file->legal_folder_id);
        $targetDirectory = $this->folderPath($target);
        $fileName = $this->uniqueFileName($targetDirectory, $file->file_name, $file->id);
        $from = $source ? $this->folderPath($source) . '/' . $file->file_name : null;

        if ($from && $this->disk()->exists($from)) {
            $this->disk()->move($from, $targetDirectory . '/' . $fileName);
        }
        $file->update(['legal_folder_id' => $target->id, 'file_name' => $fileName]);

        return response()->json(['status' => 200]);
    }

    /**
     * Download one document. `$business_id` is used by the renter route for documents shared with tenants.
     */
    public function legal_document_download($document, $business_id = null)
    {
        $ownerId = $business_id ? (int) $business_id : business_owner_id();
        $file = Legal::forBusiness($ownerId)->where('file_name', $document)->first();
        abort_unless($file, 404);
        $folder = LegalFolder::forBusiness($ownerId)->find($file->legal_folder_id);
        abort_unless($folder, 404);

        $path = $this->folderPath($folder, $ownerId) . '/' . $file->file_name;
        abort_unless($this->disk()->exists($path), 404, 'This document is missing from storage.');

        return $this->disk()->download($path, $file->file_name);
    }

    public function legal_folder_download($slug)
    {
        $folder = LegalFolder::forBusiness()->where('slug', $slug)->first();
        abort_unless($folder, 404);

        $files = $this->disk()->exists($this->folderPath($folder)) ? $this->disk()->allFiles($this->folderPath($folder)) : [];
        abort_if(empty($files), 404, 'This folder has no files to download.');

        $zipPath = tempnam(sys_get_temp_dir(), 'legal');
        $zip = new ZipArchive();
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Failed to create a zip archive.');
        $prefix = $this->folderPath($folder) . '/';
        foreach ($files as $file) {
            $zip->addFile($this->disk()->path($file), Str::after($file, $prefix));
        }
        $zip->close();

        return response()->download($zipPath, $folder->name . '.zip')->deleteFileAfterSend(true);
    }

    /** Rename a folder (the page sends the previous and new names). */
    public function legal_folder_updated(Request $request)
    {
        $previous = trim((string) ($request->combinedObject['previousFolderName'] ?? ''));
        $name = $this->validFolderName($request->combinedObject['NewFolderName'] ?? null);
        if (!$name) {
            return $this->failed('Enter a folder name of up to 100 characters without slashes.');
        }

        $ownerId = business_owner_id();
        $folder = LegalFolder::forBusiness($ownerId)->where('name', $previous)->first()
            ?? LegalFolder::forBusiness($ownerId)->where('slug', Str::slug($previous))->first();
        if (!$folder) {
            return $this->failed('Folder not found.', 404);
        }
        if ($folder->name === $name) {
            return response()->json(['status' => 200]);
        }
        if (LegalFolder::forBusiness($ownerId)->where('id', '!=', $folder->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            return $this->failed('A folder with this name already exists.');
        }

        $from = $this->folderPath($folder);
        DB::transaction(function () use ($folder, $name, $ownerId) {
            $folder->update(['name' => $name, 'slug' => $this->uniqueSlug($name, $ownerId, $folder->id)]);
        });
        if ($this->disk()->exists($from)) {
            $this->disk()->move($from, $this->folderPath($folder->fresh()));
        } else {
            $this->disk()->makeDirectory($this->folderPath($folder->fresh()));
        }

        return response()->json(['status' => 200]);
    }

    /** Delete one document (the page sends its file name). */
    public function destroyFIle($fileName)
    {
        $file = Legal::forBusiness()->where('file_name', $fileName)->first();
        if (!$file) {
            return $this->failed('Document not found.', 404);
        }

        $folder = LegalFolder::forBusiness()->find($file->legal_folder_id);
        $path = $folder ? $this->folderPath($folder) . '/' . $file->file_name : null;
        if ($path && $this->disk()->exists($path)) {
            $size = $this->disk()->size($path);
            $this->disk()->delete($path);
            app(StorageUsageService::class)->decrementUsage($size, business_owner_id());
        }
        $file->delete();

        return response()->json(['status' => 200]);
    }

    /** Delete folders (comma-separated IDs) with their documents. */
    public function destroy($id)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $id)));
        $folders = LegalFolder::forBusiness()->whereIn('id', $ids)->get();
        if ($folders->isEmpty()) {
            return $this->failed('Folder not found.', 404);
        }

        $freed = 0;
        foreach ($folders as $folder) {
            $path = $this->folderPath($folder);
            if ($this->disk()->exists($path)) {
                foreach ($this->disk()->allFiles($path) as $file) {
                    $freed += $this->disk()->size($file);
                }
                $this->disk()->deleteDirectory($path);
            }
            Legal::forBusiness()->where('legal_folder_id', $folder->id)->delete();
            $folder->delete();
        }
        if ($freed > 0) {
            app(StorageUsageService::class)->decrementUsage($freed, business_owner_id());
        }

        return response()->json(['status' => 200]);
    }

    private function uniqueSlug(string $name, int $ownerId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'folder';
        $slug = $base;
        $n = 2;
        while (LegalFolder::forBusiness($ownerId)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    /** Keep the original name, adding " (2)", " (3)" … when a file with that name already exists. */
    private function uniqueFileName(string $directory, string $original, ?int $ignoreId = null): string
    {
        $original = str_replace(['/', '\\'], '-', $original);
        $name = pathinfo($original, PATHINFO_FILENAME) ?: 'document';
        $extension = pathinfo($original, PATHINFO_EXTENSION);
        $candidate = $original;
        $n = 2;
        while ($this->disk()->exists($directory . '/' . $candidate)
            || Legal::forBusiness()->where('file_name', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $candidate = $name . ' (' . $n++ . ')' . ($extension ? '.' . $extension : '');
        }
        return $candidate;
    }
}
