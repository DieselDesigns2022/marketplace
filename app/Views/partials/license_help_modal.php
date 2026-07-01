<div class="license-modal" data-license-modal hidden>
    <div class="license-modal-backdrop" data-license-modal-close></div>
    <section class="license-modal-card" role="dialog" aria-modal="true" aria-labelledby="license-modal-title">
        <button type="button" class="license-modal-close" data-license-modal-close aria-label="Close license details">&times;</button>
        <h2 id="license-modal-title" data-license-modal-title>License details</h2>
        <div class="license-modal-body" data-license-modal-body></div>
    </section>
</div>
<script>
(function(){
    if (window.assetMothLicenseHelpModalReady) return;
    window.assetMothLicenseHelpModalReady = true;

    function modal() {
        return document.querySelector('[data-license-modal]');
    }

    function closeModal() {
        const box = modal();
        if (!box) return;
        box.hidden = true;
        document.body.classList.remove('license-modal-open');
    }

    function openModal(trigger) {
        const box = modal();
        const text = trigger.querySelector('.license-help-text');
        if (!box || !text) return;

        const title = trigger.getAttribute('aria-label') || 'License details';
        box.querySelector('[data-license-modal-title]').textContent = title;
        box.querySelector('[data-license-modal-body]').textContent = text.innerText.trim();
        box.hidden = false;
        document.body.classList.add('license-modal-open');

        const close = box.querySelector('[data-license-modal-close]');
        if (close) close.focus();
    }

    document.addEventListener('click', function(event) {
        const close = event.target.closest('[data-license-modal-close]');
        if (close) {
            event.preventDefault();
            closeModal();
            return;
        }

        const trigger = event.target.closest('.license-help');
        if (!trigger) return;

        event.preventDefault();
        event.stopPropagation();
        openModal(trigger);
    });

    document.addEventListener('keydown', function(event) {
        const trigger = event.target.closest('.license-help');
        if (trigger && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openModal(trigger);
            return;
        }

        if (event.key === 'Escape') {
            closeModal();
        }
    });
})();
</script>
