import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'tab',
        'panel',
    ];

    static values = {
        active: {
            type: String,
            default: 'login',
        },
    };

    connect() {
        this.activate(this.activeValue);
    }

    change(event) {
        const view = event.currentTarget.dataset.authView;

        if (!view) {
            return;
        }

        this.activate(view);
    }

    activate(view) {
        if (!['login', 'register'].includes(view)) {
            view = 'login';
        }

        this.activeValue = view;

        this.updatePanels(view);
        this.updateTabs(view);
    }

    updatePanels(view) {
        this.panelTargets.forEach((panel) => {
            const isActive = panel.dataset.authView === view;

            panel.classList.toggle('hidden', !isActive);
        });
    }

    updateTabs(view) {
        this.tabTargets.forEach((tab) => {
            const isActive = tab.dataset.authView === view;

            const activeClasses = this.getClasses(
                tab.dataset.activeClass
            );

            const inactiveClasses = this.getClasses(
                tab.dataset.inactiveClass
            );

            tab.classList.remove(
                ...activeClasses,
                ...inactiveClasses
            );

            tab.classList.add(
                ...(isActive ? activeClasses : inactiveClasses)
            );

            tab.setAttribute(
                'aria-selected',
                isActive ? 'true' : 'false'
            );
        });
    }

    getClasses(classes) {
        if (!classes) {
            return [];
        }

        return classes
            .split(' ')
            .map((className) => className.trim())
            .filter(Boolean);
    }
}
