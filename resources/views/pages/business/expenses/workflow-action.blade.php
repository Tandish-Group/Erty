<form method="POST" action="{{ route('business.expenses.workflow.act', $expense->id) }}" class="border-top pt-4 mt-4">
    @csrf<input type="hidden" name="action" value="{{ $action }}">
    @if($note)<label for="note-{{ $action }}" class="form-label">{{ $note }}</label><textarea id="note-{{ $action }}" name="note" maxlength="1000" class="form-control mb-3" @required(in_array($action, ['void', 'return']))></textarea>@endif
    <label class="d-block mb-3"><input required type="checkbox" name="confirmed" value="1"> I reviewed this record and confirm this action.</label>
    <button class="btn {{ $action === 'void' ? 'btn-light-danger' : 'btn-light-primary' }}">{{ $label }}</button>
</form>
