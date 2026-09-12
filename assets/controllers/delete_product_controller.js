import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'cancelButton'
    ];

    open() {
        this.modalTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        if (this.hasCancelButtonTarget) {
            this.cancelButtonTarget.focus();
        }
    }

    close() {
        this.modalTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    closeBackground(event) {
        if (event.target === event.currentTarget) {
            this.close();
        }
    }
}
