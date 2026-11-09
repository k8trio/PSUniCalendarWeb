document.addEventListener('keydown', function (event) {
    if ((!event.ctrlKey && !event.metaKey) || event.altKey) return;

    var target = event.target;
    if (target.isContentEditable || target.closest('[contenteditable="true"]')) return;
    if (target.matches('textarea, input:not([type]), input[type="text"], input[type="search"], input[type="url"], input[type="tel"], input[type="email"], input[type="password"], input[type="number"], [role="textbox"]')) return;

    var key = event.key.toLowerCase();
    var isUndo = key === 'z' && !event.shiftKey;
    var isRedo = key === 'y' || (key === 'z' && event.shiftKey);
    if (!isUndo && !isRedo) return;

    var commandForm = document.getElementById(isUndo ? 'office-undo-command' : 'office-redo-command');
    if (!commandForm || commandForm.dataset.commandEnabled !== 'true') return;

    event.preventDefault();
    commandForm.requestSubmit();
});
