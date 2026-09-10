import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'closeButton',
        'messages',
        'badge'
    ];

    static values = {
        autoOpen: Boolean,
        readUrl: String,
        csrf: String
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

        this.markAsRead();
    }

    close() {
        this.modalTarget.classList.add('hidden');

        document.body.classList.remove(
            'overflow-hidden'
        );

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
        if (event.target === event.currentTarget) {
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

    async markAsRead() {
        if (!this.hasReadUrlValue) {
            return;
        }

        try {
            const body = new URLSearchParams();

            body.append(
                '_token',
                this.csrfValue
            );

            const response = await fetch(
                this.readUrlValue,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded',
                        'X-Requested-With':
                            'XMLHttpRequest'
                    },
                    body: body.toString()
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data.success) {
                return;
            }

            /*
             * La discussion vient d'être lue :
             * on fait disparaître le badge immédiatement.
             */
            if (this.hasBadgeTarget) {
                this.badgeTarget.remove();
            }
        } catch (error) {
            console.error(
                'Impossible de marquer la discussion comme lue.',
                error
            );
        }
    }
}
