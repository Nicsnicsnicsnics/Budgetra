/**
 * Repaints the signed-in traveler's avatar everywhere it appears, without a
 * page load.
 *
 * Saving on /profile posts with fetch(), so nothing re-renders on its own.
 * Three places show that photo: the card on /profile, the sidebar, and your
 * own rows in an attraction's reviews. The sidebar is the awkward one — it
 * sits inside @persist, so wire:navigate never re-renders it and it would
 * keep the old picture for the rest of the session.
 *
 * Every one of those spots carries data-user-avatar. An element that is
 * already an <img> just gets a new src; a initials placeholder carries
 * data-avatar-img-class naming the classes its <img> replacement needs,
 * because the image and the placeholder are not always styled alike (a review
 * placeholder is .atd-review-avatar, its photo .atd-review-avatar-img too).
 *
 * The URL is kept in sessionStorage so pages reached by wire:navigate after
 * the save — an attraction you open next — get it too. A real page load
 * clears it: server-rendered markup is the truth, and a stale entry would
 * outlive a photo that had since been replaced.
 */
(function () {
    var KEY = 'budgetra:avatar-url';

    function paint(url) {
        if (!url) return;

        document.querySelectorAll('[data-user-avatar]').forEach(function (el) {
            if (el.tagName === 'IMG') {
                el.src = url;
                el.style.display = '';
                return;
            }

            var img = document.createElement('img');
            img.src = url;
            img.alt = el.getAttribute('alt') || 'Profile';
            img.className = el.getAttribute('data-avatar-img-class') || el.className;
            img.setAttribute('data-user-avatar', '');
            el.replaceWith(img);
        });
    }

    // Called by the profile form once the server confirms the new photo.
    window.budgetraSyncAvatar = function (url) {
        if (!url) return;
        try { sessionStorage.setItem(KEY, url); } catch (e) { /* private mode */ }
        paint(url);
    };

    // This file runs once per real page load and never again across
    // wire:navigate, which makes right here the moment to forget an older
    // save: the markup that just arrived was rendered from the database, so
    // anything remembered is either identical or out of date.
    try { sessionStorage.removeItem(KEY); } catch (e) { /* private mode */ }

    document.addEventListener('livewire:navigated', function () {
        var url = null;
        try { url = sessionStorage.getItem(KEY); } catch (e) { /* private mode */ }
        paint(url);
    });
})();
