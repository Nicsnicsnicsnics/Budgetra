/**
 * Sign-in and Create Account: what happens when required fields are empty.
 *
 * The browser's own "Please fill out this field" bubble is suppressed, so this
 * has to say it instead. preventDefault() on `invalid` cancels only that popup
 * — the submit stays blocked — and the field itself carries the message: it
 * holds the error highlight in style.css until something valid is typed in.
 *
 * Shared by both auth pages rather than pasted into each, because they had
 * already drifted apart once.
 */
(function () {
    var form = document.querySelector('.auth-wrapper form');
    if (!form) return;

    var focusedThisAttempt = false;

    // `invalid` fires once per failing field on a submit attempt, so there is
    // nothing to re-validate by hand. It does not bubble, hence the capture
    // phase — a plain listener on the form would never see it.
    form.addEventListener('invalid', function (e) {
        e.preventDefault();

        e.target.classList.add('is-invalid');

        // Cancelling the bubble can take the browser's own focus-the-first-one
        // with it, so put the cursor there. Every `invalid` in one attempt
        // fires synchronously and in document order, so the first is the one
        // to land on; the timeout clears the latch once that burst is over.
        if (!focusedThisAttempt) {
            focusedThisAttempt = true;
            e.target.focus();
            setTimeout(function () { focusedThisAttempt = false; }, 0);
        }
    }, true);

    // Typing clears it, on `input` rather than `change` so the highlight goes
    // the moment the field stops being empty instead of waiting for a blur.
    form.addEventListener('input', function (e) {
        if (e.target.checkValidity && e.target.checkValidity()) {
            e.target.classList.remove('is-invalid');
        }
    });
})();
