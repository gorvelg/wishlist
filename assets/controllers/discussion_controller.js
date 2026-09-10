import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'closeButton',
        'messages'
    ];

    static values = {
        autoOpen: Boolean
    };

    connect() {
        if (this.autoOpenValue) {
            this.open();
        }
    }

    open() {
        this.modalTarget.classList.remove('hidden');

        document.body.classList.add(
            'overflow-hidden'
        );

        this.closeButtonTarget.focus();

        this.scrollToBottom();
    }

    close() {
        this.modalTarget.classList.add('hidden');

        document.body.classList.remove(
            'overflow-hidden'
        );

        /*
         * On retire ?discussion=42 de l'URL.
         *
         * Sinon un refresh rouvrirait la modale.
         */
        const url = new URL(
            window.location.href
        );

        url.searchParams.delete(
            'discussion'
        );

        window.history.replaceState(
            {},
            '',
            url
        );
    }

    closeBackground(event) {
        if (
            event.target === event.currentTarget
        ) {
            this.close();
        }
    }

    scrollToBottom() {
        if (!this.hasMessagesTarget) {
            return;
        }

        this.messagesTarget.scrollTop =
            this.messagesTarget.scrollHeight;
    }
}
