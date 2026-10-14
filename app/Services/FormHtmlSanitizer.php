<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist cleaner for template bodies written in the rich-text editor.
 *
 * The HTML is rendered with v-html for every employee and fed to dompdf, so it
 * must not carry scripts, event handlers or remote content no matter what was
 * posted. Only the markup the editor itself produces survives: anything else
 * is unwrapped (text kept) or, for script-like elements, dropped with its
 * content. No purifier package is used because the cPanel deploy does not run
 * composer install -- a new vendor dependency would never reach production.
 */
class FormHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'h1', 'h2', 'h3', 'h4',
        'ul', 'ol', 'li', 'blockquote', 'hr',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'colgroup', 'col',
        'span', 'img', 'sub', 'sup',
    ];

    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta',
        'form', 'input', 'button', 'textarea', 'select', 'svg', 'math',
        'template', 'noscript', 'video', 'audio',
    ];

    private const ALIGNABLE = ['p', 'h1', 'h2', 'h3', 'h4', 'td', 'th'];

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="pact-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('pact-root');

        if (! $root) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        // Copy first: unwrapping/removing mutates the live list.
        foreach (iterator_to_array($parent->childNodes) as $node) {

            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $parent->removeChild($node);

                continue;
            }

            $this->cleanChildren($node);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }

                $parent->removeChild($node);

                continue;
            }

            // Images only from our own upload store: anything else (a remote
            // URL, a data: URI) would be fetched by every reader's browser and
            // by dompdf, so it is dropped rather than unwrapped.
            if ($tag === 'img' && ! FormImageService::isOwnUrl($node->getAttribute('src'))) {
                $parent->removeChild($node);

                continue;
            }

            $this->cleanAttributes($node, $tag);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $keep = [];

        // Only individual, known style declarations survive, rebuilt from
        // parsed numbers -- never the posted style string itself.
        $style = $element->getAttribute('style');
        $styles = [];

        if (in_array($tag, self::ALIGNABLE, true)) {
            $align = $this->textAlign($style);

            if ($align) {
                $styles[] = "text-align: {$align}";
            }
        }

        if (in_array($tag, ['td', 'th'], true)) {
            foreach (['colspan', 'rowspan'] as $span) {
                $value = (int) $element->getAttribute($span);

                if ($value > 1 && $value <= 50) {
                    $keep[$span] = (string) $value;
                }
            }

            // Column widths dragged in the editor: kept as `colwidth` for the
            // editor to read back, and as a CSS width so the employee page and
            // dompdf (which ignores <colgroup>) lay the column out the same.
            $colwidth = $element->getAttribute('colwidth');

            if (preg_match('/^\d{1,4}(,\d{1,4}){0,49}$/', $colwidth)) {
                $keep['colwidth'] = $colwidth;
                $styles[] = 'width: '.min(3000, array_sum(array_map('intval', explode(',', $colwidth)))).'px';
            }
        }

        if ($tag === 'tr') {
            $height = $this->pixels($style, 'height', 500);

            if ($height) {
                $styles[] = "height: {$height}px";

                // dompdf ignores a row's height and only sizes cells, so the
                // height is repeated on each cell. The editor reads it back
                // from the row; the cells' copy is output only.
                foreach ($element->childNodes as $cell) {
                    if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                        $cellStyle = $cell->getAttribute('style');
                        $cell->setAttribute('style', ltrim($cellStyle.'; height: '.$height.'px', '; '));
                    }
                }
            }
        }

        if ($tag === 'table') {
            if ($element->hasAttribute('data-borderless')) {
                $keep['data-borderless'] = 'true';
            }

            // Set by the editor from the column widths: "width" once every
            // column is sized, "min-width" before that.
            foreach (['width', 'min-width'] as $property) {
                $value = $this->pixels($style, $property, 3000);

                if ($value) {
                    $styles[] = "{$property}: {$value}px";
                }
            }
        }

        if ($tag === 'col') {
            foreach (['width', 'min-width'] as $property) {
                $value = $this->pixels($style, $property, 3000);

                if ($value) {
                    $styles[] = "{$property}: {$value}px";
                }
            }
        }

        if ($tag === 'img') {
            $keep['src'] = FormImageService::normalizeUrl($element->getAttribute('src'));

            foreach (['width', 'height'] as $size) {
                $value = (int) $element->getAttribute($size);

                if ($value > 0 && $value <= 2000) {
                    $keep[$size] = (string) $value;
                }
            }

            $alt = mb_substr(trim($element->getAttribute('alt')), 0, 200);

            if ($alt !== '') {
                $keep['alt'] = $alt;
            }
        }

        if ($tag === 'span') {
            $variable = $element->getAttribute('data-variable');

            if (preg_match('/^[a-z_]{1,50}$/', $variable)) {
                $keep['data-variable'] = $variable;
            }

            // The Tab key's fixed-width gap.
            if ($element->hasAttribute('data-tab')) {
                $keep['data-tab'] = '1';
            }

            // Text styling from the editor: only configured fonts and sizes,
            // and colours as plain hex.
            $font = $this->fontFamily($style);

            if ($font) {
                $styles[] = "font-family: {$font}";
            }

            if (preg_match('/(?:^|;)\s*font-size\s*:\s*(\d{1,2}(?:\.\d)?)pt/i', $style, $match)
                && in_array((float) $match[1], array_map('floatval', config('forms.font_sizes', [])), true)) {
                $styles[] = 'font-size: '.(float) $match[1].'pt';
            }

            foreach (['color', 'background-color'] as $property) {
                $colour = $this->colour($style, $property);

                if ($colour) {
                    $styles[] = "{$property}: {$colour}";
                }
            }
        }

        if ($styles !== []) {
            $keep['style'] = implode('; ', $styles);
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }

        foreach ($keep as $name => $value) {
            $element->setAttribute($name, $value);
        }
    }

    /**
     * A positive pixel value for one CSS property, capped; null otherwise.
     * Anchored so "min-width" is not read as "width".
     */
    private function pixels(string $style, string $property, int $max): ?int
    {
        if (! preg_match('/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*(\d{1,5})(?:\.\d+)?px/i', $style, $match)) {
            return null;
        }

        $value = (int) $match[1];

        return $value > 0 ? min($value, $max) : null;
    }

    /**
     * The configured font-family value matching the style, compared without
     * quotes or spacing (browsers re-quote the names when reading them back).
     */
    private function fontFamily(string $style): ?string
    {
        if (! preg_match('/(?:^|;)\s*font-family\s*:\s*([^;]+)/i', $style, $match)) {
            return null;
        }

        $normalize = fn (string $value) => strtolower((string) preg_replace('/[\s\'"]+/', '', $value));

        foreach (config('forms.fonts', []) as $font) {
            if ($normalize($font['css']) === $normalize($match[1])) {
                return $font['css'];
            }
        }

        return null;
    }

    /**
     * A colour as #rrggbb, from either hex or the rgb() form browsers produce.
     * Anchored so "background-color" is not read as "color".
     */
    private function colour(string $style, string $property): ?string
    {
        $prefix = '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*';

        if (preg_match($prefix.'#([0-9a-f]{6})/i', $style, $match)) {
            return '#'.strtolower($match[1]);
        }

        if (preg_match($prefix.'rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)/i', $style, $match)) {
            return sprintf('#%02x%02x%02x', min(255, (int) $match[1]), min(255, (int) $match[2]), min(255, (int) $match[3]));
        }

        return null;
    }

    private function textAlign(string $style): ?string
    {
        return preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $style, $match)
            ? strtolower($match[1])
            : null;
    }
}
