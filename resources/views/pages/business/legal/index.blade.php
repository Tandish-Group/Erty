@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Legal'])

@php
    $canCreate = auth()->user()->hasBusinessPermission('legal_documents', 'create');
    $canEdit = auth()->user()->hasBusinessPermission('legal_documents', 'edit');
    $canDelete = auth()->user()->hasBusinessPermission('legal_documents', 'delete');
    $canDownload = auth()->user()->hasBusinessPermission('legal_documents', 'download');
    $icon = fn (string $ext) => match (true) {
        $ext === 'pdf' => ['fa-file-pdf', 'is-pdf'],
        in_array($ext, ['doc', 'docx'], true) => ['fa-file-word', 'is-word'],
        in_array($ext, ['xls', 'xlsx'], true) => ['fa-file-excel', 'is-excel'],
        in_array($ext, ['jpg', 'jpeg', 'png'], true) => ['fa-file-image', 'is-image'],
        default => ['fa-file-alt', ''],
    };
@endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace legal-library" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Documents</span>
                <h1>Legal documents</h1>
                <p>Lease agreements, contracts, notices and other paperwork for your properties and tenants, kept in folders.</p>
            </div>
            <div class="tenant-heading-actions">
                @if($canCreate)
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#legal-folder-modal" data-folder-mode="create"><i class="fas fa-folder-plus me-1" aria-hidden="true"></i>New folder</button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#legal-upload-modal" @disabled($folders->isEmpty()) title="{{ $folders->isEmpty() ? 'Create a folder first' : '' }}"><i class="fas fa-upload me-1" aria-hidden="true"></i>Upload documents</button>
                @endif
            </div>
        </header>

        <div class="tenant-metrics legal-metrics" aria-label="Document totals">
            <div><span>Folders</span><strong>{{ $folders->count() }}</strong><small>Ways your documents are grouped</small></div>
            <div><span>Documents</span><strong>{{ $documents->count() }}</strong><small>{{ $documents->where('exists', false)->count() ? $documents->where('exists', false)->count() . ' missing from storage' : 'All files present' }}</small></div>
            <div><span>Space used</span><strong>{{ file_size($totalBytes) }}</strong><small>Across all legal documents</small></div>
        </div>

        @if($folders->isEmpty())
            <section class="tenant-panel legal-empty-state">
                <div class="tenant-empty">
                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                    <h3>Start with a folder</h3>
                    <p>Folders keep documents together, for example one per property or one for each kind of agreement. Create one, then upload documents into it.</p>
                    @if($canCreate)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#legal-folder-modal" data-folder-mode="create">Create a folder</button>@endif
                </div>
            </section>
        @else
        <div class="legal-layout">
            <nav class="tenant-panel tenant-panel--padded legal-folders" aria-label="Folders">
                <div class="legal-folders-heading">Folders</div>
                <ul class="legal-folder-list">
                    <li><button type="button" class="legal-folder is-active" data-folder-filter="" aria-pressed="true"><i class="fas fa-layer-group" aria-hidden="true"></i><span>All documents</span><em>{{ $documents->count() }}</em></button></li>
                    @foreach($folders as $folder)
                        <li class="legal-folder-row">
                            <button type="button" class="legal-folder" data-folder-filter="{{ $folder->id }}" aria-pressed="false"><i class="fas fa-folder" aria-hidden="true"></i><span>{{ $folder->name }}</span><em>{{ $folder->document_count }}</em></button>
                            @if($canEdit || $canDownload || $canDelete)
                            <div class="dropdown">
                                <button type="button" class="btn btn-icon btn-sm btn-light legal-folder-menu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $folder->name }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @if($canEdit)<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#legal-folder-modal" data-folder-mode="rename" data-folder-name="{{ $folder->name }}">Rename</button></li>@endif
                                    @if($canDownload && $folder->document_count)<li><a class="dropdown-item" href="{{ url('/download/legal_folder/' . $folder->slug) }}">Download as zip</a></li>@endif
                                    @if($canDelete)<li><button type="button" class="dropdown-item text-danger" data-delete-folder="{{ $folder->id }}" data-folder-name="{{ $folder->name }}" data-folder-count="{{ $folder->document_count }}">Delete folder</button></li>@endif
                                </ul>
                            </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <section class="tenant-panel tenant-panel--padded legal-documents" aria-labelledby="legal-documents-heading">
                <div class="legal-documents-toolbar">
                    <h2 id="legal-documents-heading" data-folder-title>All documents</h2>
                    <label class="visually-hidden" for="legal-search">Search documents</label>
                    <input type="search" id="legal-search" class="form-control" placeholder="Search by name or type">
                </div>

                <div class="tenant-table-scroll" role="region" aria-label="Documents" tabindex="0">
                    <table class="table ws-table legal-table">
                        <thead><tr><th>Name</th><th>Type</th><th>Folder</th><th class="text-end">Size</th><th>Added</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody>
                        @foreach($documents as $doc)
                            @php [$fa, $tone] = $icon($doc['extension']); @endphp
                            <tr data-document data-folder-id="{{ $doc['folder_id'] }}" data-search="{{ mb_strtolower($doc['name'] . ' ' . $doc['type']) }}">
                                <td>
                                    <div class="legal-file">
                                        <span class="legal-file-icon {{ $tone }}"><i class="fas {{ $fa }}" aria-hidden="true"></i></span>
                                        <span class="legal-file-name">
                                            @if($canDownload && $doc['exists'])<a href="{{ url('/download/legal_document/' . rawurlencode($doc['name'])) }}">{{ $doc['name'] }}</a>@else<strong>{{ $doc['name'] }}</strong>@endif
                                            @unless($doc['exists'])<span class="badge badge-light-danger ms-2" title="The record exists but the file is not in storage. Upload it again or delete this record.">File missing</span>@endunless
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $doc['type'] }}</td>
                                <td>{{ $doc['folder'] }}</td>
                                <td class="text-end text-nowrap">{{ $doc['exists'] ? file_size($doc['bytes']) : '—' }}</td>
                                <td class="text-nowrap">{{ $doc['added']?->format('j M Y') }}</td>
                                <td class="text-end">
                                    @if($canEdit || $canDelete || ($canDownload && $doc['exists']))
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-icon btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $doc['name'] }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @if($canDownload && $doc['exists'])<li><a class="dropdown-item" href="{{ url('/download/legal_document/' . rawurlencode($doc['name'])) }}">Download</a></li>@endif
                                            @if($canEdit && $folders->count() > 1)<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#legal-move-modal" data-move-id="{{ $doc['id'] }}" data-move-name="{{ $doc['name'] }}" data-move-folder="{{ $doc['folder_id'] }}">Move to another folder</button></li>@endif
                                            @if($canDelete)<li><button type="button" class="dropdown-item text-danger" data-delete-document="{{ $doc['name'] }}">Delete</button></li>@endif
                                        </ul>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                            <tr data-documents-empty @if($documents->isNotEmpty()) hidden @endif>
                                <td colspan="6" class="recon-empty"><i class="fas fa-inbox" aria-hidden="true"></i><span data-empty-text>{{ $documents->isEmpty() ? 'No documents yet. Upload the first ones into a folder.' : 'No documents here.' }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        @endif
    </div>
</div>

{{-- New folder / rename --}}
<div class="modal fade" id="legal-folder-modal" tabindex="-1" aria-labelledby="legal-folder-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="legal-folder-form">
            <div class="modal-header"><h2 class="modal-title fs-4" id="legal-folder-title">New folder</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="previous">
                <label class="form-label" for="legal-folder-name">Folder name</label>
                <input type="text" class="form-control" id="legal-folder-name" name="name" maxlength="100" required placeholder="For example: Riverside leases">
                <div class="form-text">Up to 100 characters, without slashes.</div>
                <div class="invalid-feedback d-block" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-submit-label>Create folder</button></div>
        </form>
    </div>
</div>

{{-- Upload --}}
<div class="modal fade" id="legal-upload-modal" tabindex="-1" aria-labelledby="legal-upload-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="legal-upload-form">
            <div class="modal-header"><h2 class="modal-title fs-4" id="legal-upload-title">Upload documents</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-sm-6">
                        <label class="form-label" for="legal-upload-type">Document type</label>
                        <select class="form-select" id="legal-upload-type" name="type" required>
                            <option value="">Choose a type</option>
                            @foreach($types as $type)<option value="{{ $type->id }}">{{ $type->legal_type }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="legal-upload-folder">Folder</label>
                        <select class="form-select" id="legal-upload-folder" name="folder" required>
                            <option value="">Choose a folder</option>
                            @foreach($folders as $folder)<option value="{{ $folder->id }}">{{ $folder->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="legal-upload-files">Files</label>
                        <input type="file" class="form-control" id="legal-upload-files" name="files" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt">
                        <div class="form-text">PDF, Word, Excel, images or text, up to 20 MB each. Several files can be chosen at once.</div>
                    </div>
                </div>
                <ul class="legal-upload-list" data-upload-list></ul>
                <div class="invalid-feedback d-block" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Upload</button></div>
        </form>
    </div>
</div>

{{-- Move --}}
<div class="modal fade" id="legal-move-modal" tabindex="-1" aria-labelledby="legal-move-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="legal-move-form">
            <div class="modal-header"><h2 class="modal-title fs-4" id="legal-move-title">Move document</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="file_id">
                <p class="text-muted fs-7" data-move-label></p>
                <label class="form-label" for="legal-move-folder">Move to</label>
                <select class="form-select" id="legal-move-folder" name="folder" required>
                    @foreach($folders as $folder)<option value="{{ $folder->id }}">{{ $folder->name }}</option>@endforeach
                </select>
                <div class="invalid-feedback d-block" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Move</button></div>
        </form>
    </div>
</div>
@endsection
