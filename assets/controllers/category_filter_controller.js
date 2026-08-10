import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const buttons = this.element.querySelectorAll('[data-filter-category]');
        const products = this.element.querySelectorAll('[data-product-category]');
        const availableCheckbox = this.element.querySelector('[data-available-filter]');

        let selectedCategory = 'all';

        const filterProducts = () => {
            const onlyAvailable = availableCheckbox?.checked ?? false;

            products.forEach((product) => {
                const productCategory = product.dataset.productCategory;
                const productStatus = product.dataset.productStatus;

                const matchesCategory =
                    selectedCategory === 'all'
                    || productCategory === selectedCategory;

                const matchesStatus =
                    !onlyAvailable
                    || productStatus === 'available';

                if (matchesCategory && matchesStatus) {
                    product.classList.remove('hidden');
                } else {
                    product.classList.add('hidden');
                }
            });
        };

        const setActiveButton = (activeButton) => {
            buttons.forEach((button) => {
                button.classList.remove(
                    'bg-[#2D2D2D]',
                    'text-white',
                    'border-[#2D2D2D]'
                );

                button.classList.add(
                    'bg-white',
                    'text-[#2D2D2D]',
                    'border-gray-200'
                );
            });

            activeButton.classList.remove(
                'bg-white',
                'text-[#2D2D2D]',
                'border-gray-200'
            );

            activeButton.classList.add(
                'bg-[#2D2D2D]',
                'text-white',
                'border-[#2D2D2D]'
            );
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                selectedCategory = button.dataset.filterCategory;

                setActiveButton(button);
                filterProducts();
            });
        });

        availableCheckbox?.addEventListener('change', () => {
            filterProducts();
        });
    }
}
