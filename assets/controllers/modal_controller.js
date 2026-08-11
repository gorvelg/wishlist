import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];

    static values = {
        autoOpen: Boolean
    };

    connect() {
        if (
            this.autoOpenValue &&
            this.hasDialogTarget &&
            !this.dialogTarget.open
        ) {
            this.dialogTarget.showModal();
        }
    }

    open() {
        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    close() {
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }
}
