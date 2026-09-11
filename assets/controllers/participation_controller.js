import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'choices',
        'form'
    ];

    connect() {
    }

    showForm() {

        this.choicesTarget.classList.add('hidden');
        this.formTarget.classList.remove('hidden');

        const input = this.formTarget.querySelector('input[name="amount"]');

        input?.focus();
    }

    hideForm() {

        this.formTarget.classList.add('hidden');
        this.choicesTarget.classList.remove('hidden');
    }
}
