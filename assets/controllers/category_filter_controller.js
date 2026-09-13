import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const buttons = this.element.querySelectorAll('[data-filter-category]');
        const availableCheckbox = this.element.querySelector('[data-available-filter]');
        const priceSort = this.element.querySelector('[data-price-sort]');
        const productsContainer = this.element.querySelector('[data-products-container]');

        if (!productsContainer) {
            console.error('Conteneur produits introuvable.');
            return;
        }

        const products = Array.from(
            productsContainer.querySelectorAll('[data-product-item]')
        );

        let selectedCategory = 'all';

        products.forEach((product, index) => {
            product.dataset.originalIndex = index;
        });


        const getPrice = (product) => {
            const value = product.dataset.productPrice || '0';

            const normalized = value
                .replace(/\s/g, '')
                .replace(',', '.');

            const price = Number(normalized);

            return Number.isNaN(price)
                ? 0
                : price;
        };


        /*
         * =============================================
         * FILTRER
         * =============================================
         */

        const filterProducts = () => {
            const onlyAvailable = availableCheckbox?.checked || false;

            products.forEach((product) => {
                const category = product.dataset.productCategory;
                const status = product.dataset.productStatus;

                const matchesCategory =
                    selectedCategory === 'all'
                    || category === selectedCategory;

                const matchesStatus =
                    !onlyAvailable
                    || status === 'available'
                    || status === 'buying'
                ;

                /*
                 * hidden = display:none
                 *
                 * Comme on cache le wrapper entier,
                 * la grille se réorganise sans trous.
                 */
                product.classList.toggle(
                    'hidden',
                    !(matchesCategory && matchesStatus)
                );
            });
        };


        /*
         * =============================================
         * TRIER
         * =============================================
         */

        const sortProducts = () => {
            const selectedSort = priceSort?.value || 'default';

            products.sort((a, b) => {
                /*
                 * Ordre par défaut.
                 */
                if (selectedSort === 'default') {
                    return (
                        Number(a.dataset.originalIndex)
                        - Number(b.dataset.originalIndex)
                    );
                }

                const priceA = getPrice(a);
                const priceB = getPrice(b);

                /*
                 * Petit prix → gros prix.
                 */
                if (selectedSort === 'price-asc') {
                    return priceA - priceB;
                }

                /*
                 * Gros prix → petit prix.
                 */
                if (selectedSort === 'price-desc') {
                    return priceB - priceA;
                }

                return 0;
            });

            products.forEach((product) => {
                productsContainer.appendChild(product);
            });
        };


        /*
         * =============================================
         * STYLE CATÉGORIE ACTIVE
         * =============================================
         */

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


        /*
         * =============================================
         * ÉVÉNEMENTS
         * =============================================
         */

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


        priceSort?.addEventListener('change', () => {
            sortProducts();
        });
    }
}
