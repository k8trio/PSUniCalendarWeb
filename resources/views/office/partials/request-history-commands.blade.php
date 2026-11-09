<form id="office-undo-command" method="POST" action="{{ route('office.requests.undo') }}" hidden data-command-enabled="{{ $requestHistoryControls['canUndoRequest'] ? 'true' : 'false' }}">
    @csrf
    <button type="submit" tabindex="-1" aria-hidden="true">Undo</button>
</form>
<form id="office-redo-command" method="POST" action="{{ route('office.requests.redo') }}" hidden data-command-enabled="{{ $requestHistoryControls['canRedoRequest'] ? 'true' : 'false' }}">
    @csrf
    <button type="submit" tabindex="-1" aria-hidden="true">Redo</button>
</form>
