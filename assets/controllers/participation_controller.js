import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'choices',
        'form'
    ];

    connect() {
        console.log('✅ participation_controller connecté');
    }

    showForm() {
        console.log('✅ clic Offrir à plusieurs');

        this.choicesTarget.classList.add('hidden');
        this.formTarget.classList.remove('hidden');

        const input = this.formTarget.querySelector('input[name="amount"]');

        input?.focus();
    }

    hideForm() {
        console.log('✅ annulation formulaire');

        this.formTarget.classList.add('hidden');
        this.choicesTarget.classList.remove('hidden');
    }
}
