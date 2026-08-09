import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        requestAnimationFrame(() => {
            this.element.classList.remove('opacity-0', '-translate-y-2');
            this.element.classList.add('opacity-100', 'translate-y-0');
        });

        this.timeout = setTimeout(() => {
            this.hide();
        }, 3000);
    }

    hide() {
        this.element.classList.remove('opacity-100', 'translate-y-0');
        this.element.classList.add('opacity-0', '-translate-y-2');

        setTimeout(() => {
            this.element.remove();
        }, 500);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }
}
