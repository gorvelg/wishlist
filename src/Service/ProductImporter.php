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
         * L'URL vient de l'utilisateur : on interdit l'accès au réseau privé
         * du serveur (localhost, 127.0.0.1, 192.168.x.x, etc.).
         */
        $this->client = new NoPrivateNetworkHttpClient($httpClient);
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
        $url = trim($url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL invalide.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Seules les URL HTTP et HTTPS sont autorisées.');
        }

        $response = $this->client->request('GET', $url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WishlistProductImporter/1.0)',
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            ],
            'timeout' => 5,
            'max_duration' => 10,
            'max_redirects' => 3,
        ]);

        $html = $response->getContent();

        $dom = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            $html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('La page HTML ne peut pas être analysée.');
        }

        $xpath = new \DOMXPath($dom);

        /*
         * 1. JSON-LD : c'est généralement la source la plus propre
         * pour une fiche produit e-commerce.
         */
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $rawJson = trim($script->textContent);

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
                continue;
            }

            $product = $this->findProduct($data);

            if ($product === null) {
                continue;
            }

            $name = $this->cleanString($product['name'] ?? null);
            $image = $this->extractImage($product['image'] ?? null);
            $price = $this->extractPrice($product['offers'] ?? null);

            return [
                'name' => $name,
                'image' => $this->absoluteUrl($image, $url),
                'url' => $url,
                'price' => $price,
            ];
        }

        /*
         * 2. Fallback Open Graph si le site ne fournit pas de Product JSON-LD.
         */
        $name = $this->meta($xpath, 'property', 'og:title')
            ?? $this->meta($xpath, 'name', 'twitter:title');

        $image = $this->meta($xpath, 'property', 'og:image')
            ?? $this->meta($xpath, 'name', 'twitter:image');

        $price = $this->meta($xpath, 'property', 'product:price:amount')
            ?? $this->meta($xpath, 'property', 'og:price:amount');

        return [
            'name' => $this->cleanString($name),
            'image' => $this->absoluteUrl($image, $url),
            'url' => $url,
            'price' => $this->normalizePrice($price),
        ];
    }

    private function findProduct(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $type = $data['@type'] ?? null;

        if (
            $type === 'Product'
            || (
                is_array($type)
                && in_array('Product', $type, true)
            )
        ) {
            return $data;
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $product = $this->findProduct($value);

            if ($product !== null) {
                return $product;
            }
        }

        return null;
    }

    private function extractPrice(mixed $offers): ?string
    {
        if (!is_array($offers)) {
            return null;
        }

        if (array_is_list($offers)) {
            $offers = $offers[0] ?? [];
        }

        if (!is_array($offers)) {
            return null;
        }

        $price = $offers['price']
            ?? $offers['lowPrice']
            ?? null;

        return $this->normalizePrice($price);
    }

    private function normalizePrice(mixed $price): ?string
    {
        if (!is_string($price) && !is_int($price) && !is_float($price)) {
            return null;
        }

        $price = trim((string) $price);

        if ($price === '') {
            return null;
        }

        // Accepte par exemple "21,90" ou "21.90".
        $price = str_replace(["\u{00A0}", ' '], '', $price);
        $price = str_replace(',', '.', $price);

        if (!is_numeric($price)) {
            return null;
        }

        return $price;
    }

    private function extractImage(mixed $image): ?string
    {
        if (is_string($image)) {
            return $this->cleanString($image);
        }

        if (!is_array($image)) {
            return null;
        }

        if (isset($image['url']) && is_string($image['url'])) {
            return $this->cleanString($image['url']);
        }

        if (isset($image['contentUrl']) && is_string($image['contentUrl'])) {
            return $this->cleanString($image['contentUrl']);
        }

        $first = $image[0] ?? null;

        if (is_string($first)) {
            return $this->cleanString($first);
        }

        if (is_array($first)) {
            if (isset($first['url']) && is_string($first['url'])) {
                return $this->cleanString($first['url']);
            }

            if (isset($first['contentUrl']) && is_string($first['contentUrl'])) {
                return $this->cleanString($first['contentUrl']);
            }
        }

        return null;
    }

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

        $node = $xpath->query($query)->item(0);

        if (!$node instanceof \DOMElement) {
            return null;
        }

        return $this->cleanString(
            $node->getAttribute('content')
        );
    }

    private function cleanString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim(html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        return $value !== '' ? $value : null;
    }

    private function absoluteUrl(?string $value, string $productUrl): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
        ) {
            return $value;
        }

        $parts = parse_url($productUrl);

        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
        ) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return $parts['scheme'] . ':' . $value;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        if (str_starts_with($value, '/')) {
            return $origin . $value;
        }

        $path = $parts['path'] ?? '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        if ($directory === '.' || $directory === '/') {
            $directory = '';
        }

        return $origin
            . ($directory !== '' ? '/' . ltrim($directory, '/') : '')
            . '/'
            . ltrim($value, '/');
    }
}
