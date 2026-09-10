import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'button',
        'closeButton'
    ];

    connect() {
        this.previousFocus = null;
    }

    open() {
        this.previousFocus = document.activeElement;

        this.modalTarget.classList.remove('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'true');

        document.body.classList.add('overflow-hidden');

        this.closeButtonTarget.focus();
    }

    close() {
        this.modalTarget.classList.add('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'false');

        document.body.classList.remove('overflow-hidden');

        this.previousFocus?.focus();
    }

    closeBackground(event) {
        if (event.target === event.currentTarget) {
            this.close();
        }
    }
}
