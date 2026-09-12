<?php

namespace App\Service;

use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ProductImporter
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $httpClient)
    {
        /*
         * L'URL vient de l'utilisateur.
         *
         * On interdit donc l'accès au réseau privé du serveur :
         *
         * - localhost
         * - 127.0.0.1
         * - 192.168.x.x
         * - réseau Docker interne
         * - etc.
         *
         * Cela évite notamment les attaques SSRF.
         */
        $this->client = new NoPrivateNetworkHttpClient(
            $httpClient
        );
    }

    /**
     * @return array{
     *     name: ?string,
     *     image: ?string,
     *     url: string,
     *     price: ?string
     * }
     */
    public function extract(string $url): array
    {
        /*
         * ==========================================================
         * URL
         * ==========================================================
         */

        $url = trim(
            $url
        );


        if (
            !filter_var(
                $url,
                FILTER_VALIDATE_URL
            )
        ) {
            throw new \InvalidArgumentException(
                'URL invalide.'
            );
        }


        $scheme = strtolower(
            (string) parse_url(
                $url,
                PHP_URL_SCHEME
            )
        );


        if (
            !in_array(
                $scheme,
                [
                    'http',
                    'https',
                ],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Seules les URL HTTP et HTTPS sont autorisées.'
            );
        }


        /*
         * ==========================================================
         * TÉLÉCHARGEMENT DE LA PAGE
         * ==========================================================
         */

        $response = $this->client->request(
            'GET',
            $url,
            [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; WishlistProductImporter/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                ],

                'timeout' => 5,

                'max_duration' => 10,

                'max_redirects' => 3,
            ]
        );


        $html = $response->getContent();


        /*
         * ==========================================================
         * PARSING HTML
         * ==========================================================
         */

        $dom = new \DOMDocument();


        $previous = libxml_use_internal_errors(
            true
        );


        $loaded = $dom->loadHTML(
            $html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );


        libxml_clear_errors();


        libxml_use_internal_errors(
            $previous
        );


        if (!$loaded) {
            throw new \RuntimeException(
                'La page HTML ne peut pas être analysée.'
            );
        }


        $xpath = new \DOMXPath(
            $dom
        );


        /*
         * ==========================================================
         * RÉSULTAT
         * ==========================================================
         *
         * Contrairement à l'ancienne version :
         *
         * on ne fait PAS de return dès qu'on trouve du JSON-LD.
         *
         * Chaque source peut compléter la précédente.
         */

        $name = null;

        $image = null;

        $price = null;


        /*
         * ==========================================================
         * 1. JSON-LD
         * ==========================================================
         *
         * C'est généralement la meilleure source pour les pages
         * produit e-commerce.
         */

        $scripts = $xpath->query(
            '//script[@type="application/ld+json"]'
        );


        if ($scripts !== false) {

            foreach ($scripts as $script) {

                $rawJson = trim(
                    $script->textContent
                );


                if ($rawJson === '') {
                    continue;
                }


                try {

                    $data = json_decode(
                        $rawJson,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                } catch (\JsonException) {

                    /*
                     * Certains sites possèdent plusieurs blocs
                     * JSON-LD dont certains sont invalides.
                     *
                     * On ignore simplement celui-ci.
                     */

                    continue;
                }


                $product = $this->findProduct(
                    $data
                );


                if ($product === null) {
                    continue;
                }


                /*
                 * NOM
                 */

                if ($name === null) {

                    $name = $this->cleanString(
                        $product['name'] ?? null
                    );
                }


                /*
                 * IMAGE
                 */

                if ($image === null) {

                    $image = $this->extractImage(
                        $product['image'] ?? null
                    );
                }


                /*
                 * PRIX
                 */

                if ($price === null) {

                    $price = $this->extractPrice(
                        $product['offers'] ?? null
                    );
                }


                /*
                 * Si on a déjà tout trouvé, inutile de continuer
                 * à parcourir les autres blocs JSON-LD.
                 */

                if (
                    $name !== null
                    && $image !== null
                    && $price !== null
                ) {
                    break;
                }
            }
        }


        /*
         * ==========================================================
         * 2. OPEN GRAPH / TWITTER
         * ==========================================================
         *
         * On complète uniquement les informations manquantes.
         */

        if ($name === null) {

            $name =
                $this->meta(
                    $xpath,
                    'property',
                    'og:title'
                )
                ?? $this->meta(
                $xpath,
                'name',
                'twitter:title'
            );
        }


        if ($image === null) {

            $image =
                $this->meta(
                    $xpath,
                    'property',
                    'og:image'
                )
                ?? $this->meta(
                $xpath,
                'property',
                'og:image:url'
            )
                ?? $this->meta(
                $xpath,
                'property',
                'og:image:secure_url'
            )
                ?? $this->meta(
                    $xpath,
                    'name',
                    'twitter:image'
                );
        }


        if ($price === null) {

            $price = $this->normalizePrice(
                $this->meta(
                    $xpath,
                    'property',
                    'product:price:amount'
                )
                ?? $this->meta(
                $xpath,
                'property',
                'og:price:amount'
            )
            );
        }


        /*
         * ==========================================================
         * 3. MICRODATA / SCHEMA.ORG
         * ==========================================================
         *
         * Certains sites utilisent :
         *
         * <meta itemprop="price" content="49.99">
         *
         * ou
         *
         * <span itemprop="price" content="49.99">
         */

        if ($name === null) {

            $name =
                $this->meta(
                    $xpath,
                    'itemprop',
                    'name'
                )
                ?? $this->attribute(
                $xpath,
                '//*[@itemprop="name"]',
                'content'
            );
        }


        if ($image === null) {

            $image =
                $this->meta(
                    $xpath,
                    'itemprop',
                    'image'
                )
                ?? $this->attribute(
                $xpath,
                '//*[@itemprop="image"]',
                'content'
            )
                ?? $this->attribute(
                    $xpath,
                    '//*[@itemprop="image"]',
                    'src'
                );
        }


        if ($price === null) {

            $price = $this->normalizePrice(
                $this->meta(
                    $xpath,
                    'itemprop',
                    'price'
                )
                ?? $this->attribute(
                $xpath,
                '//*[@itemprop="price"]',
                'content'
            )
                ?? $this->attribute(
                    $xpath,
                    '//*[@itemprop="price"]',
                    'data-price'
                )
            );
        }


        /*
         * ==========================================================
         * 4. META HTML CLASSIQUES
         * ==========================================================
         */

        if ($name === null) {

            $name =
                $this->meta(
                    $xpath,
                    'name',
                    'title'
                )
                ?? $this->meta(
                $xpath,
                'name',
                'product'
            );
        }


        if ($price === null) {

            $price = $this->normalizePrice(
                $this->meta(
                    $xpath,
                    'name',
                    'price'
                )
            );
        }


        /*
         * ==========================================================
         * 5. BALISE <title>
         * ==========================================================
         *
         * Dernier fallback pour le nom.
         */

        if ($name === null) {

            $name = $this->text(
                $xpath,
                '//title'
            );
        }


        /*
         * ==========================================================
         * 6. LINK IMAGE_SRC
         * ==========================================================
         *
         * Quelques sites utilisent encore :
         *
         * <link rel="image_src" href="...">
         */

        if ($image === null) {

            $image = $this->attribute(
                $xpath,
                '//link[@rel="image_src"]',
                'href'
            );
        }


        /*
         * ==========================================================
         * URL ABSOLUE DE L'IMAGE
         * ==========================================================
         */

        $image = $this->absoluteUrl(
            $image,
            $url
        );


        /*
         * ==========================================================
         * RÉSULTAT
         * ==========================================================
         */

        return [
            'name' => $this->cleanString(
                $name
            ),

            'image' => $image,

            'url' => $url,

            'price' => $price,
        ];
    }


    /*
     * ==============================================================
     * TROUVER UN PRODUCT DANS DU JSON-LD
     * ==============================================================
     *
     * Cela supporte notamment :
     *
     * {
     *     "@type": "Product"
     * }
     *
     * mais également :
     *
     * {
     *     "@graph": [
     *         {
     *             "@type": "Product"
     *         }
     *     ]
     * }
     */

    private function findProduct(
        mixed $data
    ): ?array {

        if (!is_array($data)) {
            return null;
        }


        $type = $data['@type']
            ?? null;


        if (
            $type === 'Product'
            || (
                is_array($type)
                && in_array(
                    'Product',
                    $type,
                    true
                )
            )
        ) {
            return $data;
        }


        foreach ($data as $value) {

            if (!is_array($value)) {
                continue;
            }


            $product = $this->findProduct(
                $value
            );


            if ($product !== null) {
                return $product;
            }
        }


        return null;
    }


    /*
     * ==============================================================
     * EXTRACTION DU PRIX JSON-LD
     * ==============================================================
     */

    private function extractPrice(
        mixed $offers
    ): ?string {

        if (!is_array($offers)) {
            return null;
        }


        /*
         * Plusieurs offres.
         *
         * Exemple :
         *
         * "offers": [
         *     {...},
         *     {...}
         * ]
         */

        if (array_is_list($offers)) {

            foreach ($offers as $offer) {

                $price = $this->extractPrice(
                    $offer
                );


                if ($price !== null) {
                    return $price;
                }
            }


            return null;
        }


        /*
         * Prix classique.
         */

        $price =
            $offers['price']
            ?? $offers['lowPrice']
            ?? null;


        $normalized = $this->normalizePrice(
            $price
        );


        if ($normalized !== null) {
            return $normalized;
        }


        /*
         * ==========================================================
         * PRICE SPECIFICATION
         * ==========================================================
         *
         * Exemple :
         *
         * "offers": {
         *     "priceSpecification": {
         *         "price": "129.90"
         *     }
         * }
         */

        $priceSpecification =
            $offers['priceSpecification']
            ?? null;


        if (is_array($priceSpecification)) {

            /*
             * Plusieurs PriceSpecification.
             */

            if (
                array_is_list(
                    $priceSpecification
                )
            ) {

                foreach (
                    $priceSpecification
                    as $specification
                ) {

                    if (!is_array($specification)) {
                        continue;
                    }


                    $normalized = $this->normalizePrice(
                        $specification['price']
                        ?? null
                    );


                    if ($normalized !== null) {
                        return $normalized;
                    }
                }

            } else {

                /*
                 * Une seule PriceSpecification.
                 */

                $normalized = $this->normalizePrice(
                    $priceSpecification['price']
                    ?? null
                );


                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }


        return null;
    }


    /*
     * ==============================================================
     * NORMALISATION DU PRIX
     * ==============================================================
     *
     * Comprend notamment :
     *
     * 21.90
     * 21,90
     * 21,90 €
     * 1 299,90 €
     * 1.299,90 €
     * 1,299.90
     */

    private function normalizePrice(
        mixed $price
    ): ?string {

        if (
            !is_string($price)
            && !is_int($price)
            && !is_float($price)
        ) {
            return null;
        }


        $price = trim(
            (string) $price
        );


        if ($price === '') {
            return null;
        }


        /*
         * Suppression des espaces classiques,
         * insécables et fines insécables.
         */

        $price = str_replace(
            [
                "\u{00A0}",
                "\u{202F}",
                ' ',
            ],
            '',
            $price
        );


        /*
         * On ne conserve que :
         *
         * - chiffres
         * - virgule
         * - point
         * - signe moins
         */

        $price = preg_replace(
            '/[^\d,.\-]/u',
            '',
            $price
        );


        if (
            $price === null
            || $price === ''
        ) {
            return null;
        }


        /*
         * ==========================================================
         * VIRGULE + POINT
         * ==========================================================
         *
         * Le dernier séparateur rencontré est considéré
         * comme séparateur décimal.
         *
         * 1.299,99
         * → 1299.99
         *
         * 1,299.99
         * → 1299.99
         */

        if (
            str_contains(
                $price,
                ','
            )
            && str_contains(
                $price,
                '.'
            )
        ) {

            $lastComma =
                strrpos(
                    $price,
                    ','
                );


            $lastDot =
                strrpos(
                    $price,
                    '.'
                );


            if (
                $lastComma !== false
                && $lastDot !== false
            ) {

                if (
                    $lastComma
                    > $lastDot
                ) {

                    /*
                     * Format européen :
                     *
                     * 1.299,99
                     */

                    $price = str_replace(
                        '.',
                        '',
                        $price
                    );


                    $price = str_replace(
                        ',',
                        '.',
                        $price
                    );

                } else {

                    /*
                     * Format US :
                     *
                     * 1,299.99
                     */

                    $price = str_replace(
                        ',',
                        '',
                        $price
                    );
                }
            }

        } elseif (
            str_contains(
                $price,
                ','
            )
        ) {

            /*
             * Une ou plusieurs virgules.
             */

            $parts = explode(
                ',',
                $price
            );


            if (
                count($parts) === 2
                && strlen($parts[1]) <= 2
            ) {

                /*
                 * 1299,90
                 */

                $price =
                    $parts[0]
                    . '.'
                    . $parts[1];

            } else {

                /*
                 * 1,299
                 */

                $price = str_replace(
                    ',',
                    '',
                    $price
                );
            }

        } elseif (
            str_contains(
                $price,
                '.'
            )
        ) {

            /*
             * Plusieurs points.
             *
             * Exemple :
             *
             * 1.299.90
             *
             * Cas très rare mais on essaie tout de même
             * de rester tolérant.
             */

            if (
                substr_count(
                    $price,
                    '.'
                ) > 1
            ) {

                $parts = explode(
                    '.',
                    $price
                );


                $last = array_pop(
                    $parts
                );


                if (
                    $last !== null
                    && strlen($last) <= 2
                ) {

                    $price =
                        implode(
                            '',
                            $parts
                        )
                        . '.'
                        . $last;

                } else {

                    $price = implode(
                        '',
                        array_merge(
                            $parts,
                            [$last]
                        )
                    );
                }
            }
        }


        if (!is_numeric($price)) {
            return null;
        }


        /*
         * Pas de prix négatif.
         */

        if ((float) $price < 0) {
            return null;
        }


        return $price;
    }


    /*
     * ==============================================================
     * IMAGE JSON-LD
     * ==============================================================
     */

    private function extractImage(
        mixed $image
    ): ?string {

        /*
         * URL simple.
         */

        if (is_string($image)) {

            return $this->cleanString(
                $image
            );
        }


        if (!is_array($image)) {
            return null;
        }


        /*
         * ImageObject.
         */

        if (
            isset($image['url'])
            && is_string(
                $image['url']
            )
        ) {

            return $this->cleanString(
                $image['url']
            );
        }


        if (
            isset($image['contentUrl'])
            && is_string(
                $image['contentUrl']
            )
        ) {

            return $this->cleanString(
                $image['contentUrl']
            );
        }


        /*
         * Tableau d'images.
         */

        $first =
            $image[0]
            ?? null;


        if (is_string($first)) {

            return $this->cleanString(
                $first
            );
        }


        if (is_array($first)) {

            if (
                isset($first['url'])
                && is_string(
                    $first['url']
                )
            ) {

                return $this->cleanString(
                    $first['url']
                );
            }


            if (
                isset($first['contentUrl'])
                && is_string(
                    $first['contentUrl']
                )
            ) {

                return $this->cleanString(
                    $first['contentUrl']
                );
            }
        }


        return null;
    }


    /*
     * ==============================================================
     * META
     * ==============================================================
     *
     * Exemple :
     *
     * <meta property="og:title" content="Poussette">
     */

    private function meta(
        \DOMXPath $xpath,
        string $attribute,
        string $value
    ): ?string {

        $query = sprintf(
            '//meta[@%s="%s"]',
            $attribute,
            $value
        );


        $nodes = $xpath->query(
            $query
        );


        if ($nodes === false) {
            return null;
        }


        $node = $nodes->item(
            0
        );


        if (!$node instanceof \DOMElement) {
            return null;
        }


        return $this->cleanString(
            $node->getAttribute(
                'content'
            )
        );
    }


    /*
     * ==============================================================
     * ATTRIBUT HTML
     * ==============================================================
     *
     * Permet par exemple de lire :
     *
     * <img itemprop="image" src="...">
     *
     * ou :
     *
     * <span itemprop="price" content="29.90">
     */

    private function attribute(
        \DOMXPath $xpath,
        string $query,
        string $attribute
    ): ?string {

        $nodes = $xpath->query(
            $query
        );


        if ($nodes === false) {
            return null;
        }


        $node = $nodes->item(
            0
        );


        if (!$node instanceof \DOMElement) {
            return null;
        }


        if (
            !$node->hasAttribute(
                $attribute
            )
        ) {
            return null;
        }


        return $this->cleanString(
            $node->getAttribute(
                $attribute
            )
        );
    }


    /*
     * ==============================================================
     * CONTENU TEXTE HTML
     * ==============================================================
     *
     * Sert principalement à récupérer :
     *
     * <title>...</title>
     */

    private function text(
        \DOMXPath $xpath,
        string $query
    ): ?string {

        $nodes = $xpath->query(
            $query
        );


        if ($nodes === false) {
            return null;
        }


        $node = $nodes->item(
            0
        );


        if (!$node instanceof \DOMNode) {
            return null;
        }


        return $this->cleanString(
            $node->textContent
        );
    }


    /*
     * ==============================================================
     * NETTOYAGE CHAÎNE
     * ==============================================================
     */

    private function cleanString(
        mixed $value
    ): ?string {

        if (!is_string($value)) {
            return null;
        }


        $value = trim(
            html_entity_decode(
                $value,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );


        return $value !== ''
            ? $value
            : null;
    }


    /*
     * ==============================================================
     * URL IMAGE ABSOLUE
     * ==============================================================
     *
     * Exemple :
     *
     * /images/product.jpg
     *
     * devient :
     *
     * https://site.fr/images/product.jpg
     */

    private function absoluteUrl(
        ?string $value,
        string $productUrl
    ): ?string {

        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }


        /*
         * Déjà absolue.
         */

        if (
            str_starts_with(
                $value,
                'http://'
            )
            || str_starts_with(
                $value,
                'https://'
            )
        ) {
            return $value;
        }


        $parts = parse_url(
            $productUrl
        );


        if (
            !is_array($parts)
            || !isset(
                $parts['scheme'],
                $parts['host']
            )
        ) {
            return $value;
        }


        /*
         * URL protocol-relative :
         *
         * //cdn.site.fr/image.jpg
         */

        if (
            str_starts_with(
                $value,
                '//'
            )
        ) {

            return
                $parts['scheme']
                . ':'
                . $value;
        }


        /*
         * Origine.
         */

        $origin =
            $parts['scheme']
            . '://'
            . $parts['host'];


        if (
            isset(
                $parts['port']
            )
        ) {

            $origin .=
                ':'
                . $parts['port'];
        }


        /*
         * URL relative à la racine.
         *
         * /media/product.jpg
         */

        if (
            str_starts_with(
                $value,
                '/'
            )
        ) {

            return
                $origin
                . $value;
        }


        /*
         * URL relative au dossier courant.
         */

        $path =
            $parts['path']
            ?? '/';


        $directory = rtrim(
            str_replace(
                '\\',
                '/',
                dirname(
                    $path
                )
            ),
            '/'
        );


        if (
            $directory === '.'
            || $directory === '/'
        ) {

            $directory = '';
        }


        return
            $origin
            . (
            $directory !== ''
                ? '/'
                . ltrim(
                    $directory,
                    '/'
                )
                : ''
            )
            . '/'
            . ltrim(
                $value,
                '/'
            );
    }
}
