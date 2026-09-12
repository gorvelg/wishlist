import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'closeButton',
        'messages',
        'badge',
        'input'
    ];

    static values = {
        autoOpen: Boolean,
        readUrl: String,
        csrf: String
    };

    connect() {
        this.messagesObserver = null;

        if (this.hasMessagesTarget) {

            this.messagesObserver = new MutationObserver(() => {

                /*
                 * Un nouveau message vient d'être ajouté
                 * dans la discussion.
                 */

                if (
                    this.hasModalTarget
                    && !this.modalTarget.classList.contains('hidden')
                ) {

                    /*
                     * Si la discussion est ouverte :
                     *
                     * - on descend automatiquement
                     * - on marque les messages comme lus
                     */

                    this.scrollToBottom();

                    this.markAsRead();
                }

            });


            this.messagesObserver.observe(
                this.messagesTarget,
                {
                    childList: true
                }
            );
        }


        /*
         * Ouvrir automatiquement la discussion
         * après certaines redirections.
         */

        if (this.autoOpenValue) {
            this.open();
        }
    }


    /*
     * ==========================================================
     * DÉCONNEXION DU CONTROLLER
     * ==========================================================
     */

    disconnect() {
        if (this.messagesObserver) {

            this.messagesObserver.disconnect();

            this.messagesObserver = null;
        }
    }


    /*
     * ==========================================================
     * OUVRIR
     * ==========================================================
     */

    open() {
        this.modalTarget.classList.remove('hidden');

        document.body.classList.add(
            'overflow-hidden'
        );


        if (this.hasCloseButtonTarget) {
            this.closeButtonTarget.focus();
        }


        this.scrollToBottom();

        this.markAsRead();
    }


    /*
     * ==========================================================
     * FERMER
     * ==========================================================
     */

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


    /*
     * ==========================================================
     * FERMER EN CLIQUANT SUR LE FOND
     * ==========================================================
     */

    closeBackground(event) {
        if (
            event.target === event.currentTarget
        ) {
            this.close();
        }
    }


    /*
     * ==========================================================
     * SCROLL VERS LE DERNIER MESSAGE
     * ==========================================================
     */

    scrollToBottom() {
        if (!this.hasMessagesTarget) {
            return;
        }


        requestAnimationFrame(() => {

            this.messagesTarget.scrollTop =
                this.messagesTarget.scrollHeight;

        });
    }


    /*
     * ==========================================================
     * APRÈS ENVOI D'UN MESSAGE
     * ==========================================================
     */

    afterSubmit(event) {

        /*
         * Si Symfony renvoie une erreur,
         * on conserve le texte.
         */

        if (!event.detail.success) {
            return;
        }


        /*
         * Vider le textarea.
         */

        if (this.hasInputTarget) {

            this.inputTarget.value = '';

            this.inputTarget.focus();
        }


        /*
         * Turbo doit avoir le temps d'ajouter
         * le message dans le DOM.
         */

        requestAnimationFrame(() => {

            this.scrollToBottom();

        });
    }


    /*
     * ==========================================================
     * MARQUER LA DISCUSSION COMME LUE
     * ==========================================================
     */

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


            const data =
                await response.json();


            if (!data.success) {
                return;
            }


            /*
             * Supprimer le badge "non lu".
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
