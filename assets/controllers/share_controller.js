import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String
    }

    connect() {
        console.log('share controller connecté');
        console.log('URL à copier :', this.urlValue);
    }

    async copy() {
        console.log('clic sur copier');

        try {
            /*
             * Méthode moderne.
             * Fonctionne principalement en HTTPS.
             */
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(
                    this.urlValue
                );

                this.showSuccess();

                return;
            }

            /*
             * Fallback pour environnement local HTTP.
             */
            this.copyFallback();

        } catch (error) {
            console.error(
                'Impossible de copier le lien :',
                error
            );
        }
    }

    copyFallback() {
        const textarea = document.createElement('textarea');

        textarea.value = this.urlValue;

        /*
         * On le met hors écran.
         */
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '-9999px';

        document.body.appendChild(textarea);

        textarea.focus();
        textarea.select();

        const success = document.execCommand('copy');

        textarea.remove();

        if (success) {
            this.showSuccess();
        } else {
            console.error(
                'La copie du lien a échoué.'
            );
        }
    }

    showSuccess() {
        const originalHtml = this.element.innerHTML;

        this.element.innerHTML = '✓ Lien copié';

        setTimeout(() => {
            this.element.innerHTML = originalHtml;
        }, 2000);
    }
}
