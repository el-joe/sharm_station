<?php

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitizer for content written in the admin editor (pages, blog, FAQ answers).
 * Unknown tags are unwrapped (their text is kept); script-like tags are dropped with their content;
 * only safe attributes and http(s), mailto, tel, relative and anchor links survive.
 */
class HtmlSanitizer
{
    /**
     * Allowed tags => allowed attributes.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'title', 'target', 'rel'], 'code' => [], 'pre' => [], 'small' => [], 'sub' => [], 'sup' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['scope'], 'td' => [], 'figure' => [], 'figcaption' => [],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    /**
     * Removed together with everything inside them.
     *
     * @var list<string>
     */
    public const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'noscript', 'svg', 'math', 'template', 'link', 'meta', 'base', 'frame', 'frameset'];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__root');

        if (! $root instanceof DOMElement) {
            return e(strip_tags($html));
        }

        $this->cleanChildren($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);

                continue;
            }

            $this->cleanChildren($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            $this->cleanAttributes($child, self::ALLOWED[$tag]);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function cleanAttributes(DOMElement $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            $keep = in_array($name, $allowed, true) && match ($name) {
                'href', 'src' => $this->isSafeUrl($value, $name === 'src'),
                'target' => in_array($value, ['_blank', '_self'], true),
                'id' => (bool) preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,80}$/', $value),
                'width', 'height' => ctype_digit($value),
                default => true,
            };

            if (! $keep) {
                $element->removeAttribute($attribute->name);
            }
        }

        if (strtolower($element->tagName) === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isSafeUrl(string $url, bool $image): bool
    {
        $url = preg_replace('/[\x00-\x20]+/', '', $url) ?? '';

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        if (! $image && str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, $image ? ['https', 'http'] : ['https', 'http', 'mailto', 'tel'], true);
    }
}
