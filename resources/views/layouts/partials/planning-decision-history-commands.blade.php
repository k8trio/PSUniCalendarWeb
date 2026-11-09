<form id="planning-undo-command" method="POST" action="{{ route('planning_office.decisions.undo') }}" hidden data-command-enabled="{{ $planningDecisionHistoryControls['canUndoDecision'] ? 'true' : 'false' }}">
    @csrf
    <button type="submit" tabindex="-1" aria-hidden="true">Undo decision</button>
</form>
<form id="planning-redo-command" method="POST" action="{{ route('planning_office.decisions.redo') }}" hidden data-command-enabled="{{ $planningDecisionHistoryControls['canRedoDecision'] ? 'true' : 'false' }}">
    @csrf
    <button type="submit" tabindex="-1" aria-hidden="true">Redo decision</button>
</form>
