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

    /* ── Submitting ──────────────────────────────────────────────────────
     *
     * Posted with fetch() rather than as a normal navigation, for two
     * reasons that turn out to be the same reason:
     *
     *   The password survives a rejection. Laravel deliberately keeps
     *   passwords out of the flashed input — they would sit in the session
     *   in the clear — so a page that reloads on failure cannot bring them
     *   back, and "the email is already taken" used to cost you both
     *   password boxes as well. Here the page never reloads, so the fields
     *   are simply never emptied and nothing is stored anywhere to manage
     *   that.
     *
     *   The button can show the wait. A real navigation spins the browser
     *   tab and leaves the button looking untouched; nothing on the page can
     *   change once the request is in flight. Without navigating, the button
     *   holds a spinner for exactly as long as the request takes.
     *
     * Without JavaScript the form still posts and redirects the old way.
     */
    var busy = false;

    // Every message sits under the field it belongs to. Errors the server
    // rendered into the page are cleared here too: this submit replaces them.
    function clearErrors() {
        form.querySelectorAll('.error').forEach(function (el) { el.remove(); });
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }

    function fieldError(name, message) {
        var input = form.querySelector('[name="' + name + '"]');
        if (!input) return false;

        var group = input.closest('.form-group');
        if (!group) return false;

        input.classList.add('is-invalid');

        var note = document.createElement('div');
        note.className = 'error';
        note.textContent = message;
        group.appendChild(note);

        return true;
    }

    // For the failures that belong to no field at all — a dropped connection,
    // a server that fell over. Placed at the submit button rather than back
    // at the top of the form, where the summary banner used to be.
    function formError(message) {
        var note = document.createElement('div');
        note.className = 'error';
        note.style.textAlign = 'center';
        note.style.marginBottom = '12px';
        button.parentNode.insertBefore(note, button);
        note.textContent = message;
    }

    function showErrors(errors) {
        var unplaced = [];

        Object.keys(errors).forEach(function (name) {
            var message = [].concat(errors[name])[0];
            // A field that is not on this page would otherwise take its
            // message down with it.
            if (!fieldError(name, message)) unplaced.push(message);
        });

        unplaced.forEach(formError);

        var first = form.querySelector('.is-invalid');
        if (first) first.focus();
    }

    var button = form.querySelector('button[type="submit"]');
    if (!button) return;

    var idleLabel = button.innerHTML;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy) return;

        // The browser only fires `submit` once its own validation passes, so
        // by here the required fields are filled.
        busy = true;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        // The spinner alone, with no wording beside it. data-busy-label still
        // says what is happening, as the button's accessible name — a button
        // whose only content is a decorative icon would otherwise announce
        // itself as nothing at all.
        button.setAttribute('aria-label', button.dataset.busyLabel || 'Please wait…');
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>';

        clearErrors();

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (res) {
            // A signed-out session answers the POST with a fresh login page
            // rather than JSON; there is nothing to parse and nothing useful
            // to say, so start again on a page with a valid token.
            if (res.status === 419) { window.location.reload(); return null; }

            return res.json().catch(function () { return {}; }).then(function (data) {
                return { ok: res.ok, status: res.status, data: data };
            });
        }).then(function (r) {
            if (!r) return;

            if (r.ok) {
                // Deliberately left spinning: the next page is already on its
                // way, and putting "Sign In" back would read as nothing having
                // happened.
                window.location.href = r.data.redirect || '/';
                return;
            }

            release();
            if (r.status === 422) showErrors(r.data.errors || {});
            else formError('Something went wrong. Please try again.');
        }).catch(function () {
            release();
            formError('Could not reach the server. Check your connection and try again.');
        });
    });

    function release() {
        busy = false;
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.removeAttribute('aria-label');
        button.innerHTML = idleLabel;
    }
})();
