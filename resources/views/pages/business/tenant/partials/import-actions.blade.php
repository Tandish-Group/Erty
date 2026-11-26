{{-- Under one import table: discard an uploaded file, and import the selected rows. $type (tenant|payment|notice), $rows, $formId, $noun. --}}
<div class="imports-actions">
    <div class="imports-discard">
        @foreach($rows->groupBy(fn ($r) => $r->row->batch_id) as $batchId => $batchRows)
            <button type="button" class="btn btn-sm btn-light-danger" data-discard-batch="{{ $batchId }}" data-discard-type="{{ $type }}">
                <i class="fas fa-trash-alt me-1" aria-hidden="true"></i>Discard {{ $batchRows->count() }} {{ \Illuminate\Support\Str::plural('row', $batchRows->count()) }} uploaded {{ $batchRows->first()->row->created_at ? \Carbon\Carbon::parse($batchRows->first()->row->created_at)->format('j M, H:i') : '' }}
            </button>
        @endforeach
    </div>
    <form id="{{ $formId }}" class="form">
        @csrf
        <input type="hidden" name="batch_id" value="">
        <input type="hidden" name="selected_ids" value="">
        <input type="hidden" name="select_all_pages" value="0">
        {{-- The page script shows these once rows are selected; the discard buttons above remove a whole upload. --}}
        <button class="btn btn-light d-none" id="{{ $type }}-cancel-import-btn"><span class="indicator-label">Discard the upload</span><span class="indicator-progress">Working…<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span></button>
        <button type="button" class="btn btn-primary d-none" id="{{ $type }}-confirm-import-btn"><span class="indicator-label">Import selected {{ $noun }}</span><span class="indicator-progress">Importing…<span class="spinner-border spinner-border-sm align-middle ms-2"></span></span></button>
    </form>
</div>
