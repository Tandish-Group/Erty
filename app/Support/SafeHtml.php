<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans rich text from the editor before it is stored or shown: keeps simple formatting tags, drops every
 * attribute (so no links, styles, event handlers or images) and every other element, keeping its text.
 */
class SafeHtml
{
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4', 'pre', 'code'];

    /** Elements dropped together with their content. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'head', 'title'];

    /** Plain text from a text box: escaped, with its line breaks kept. */
    public static function fromText(?string $text): string
    {
        $text = trim(str_replace("\r\n", "\n", (string) $text));

        return $text === '' ? '' : nl2br(e($text), false);
    }

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        // Plain text (no tags): keep the line breaks.
        if ($html === strip_tags($html)) {
            return nl2br(e($html), false);
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementsByTagName('div')->item(0);
        if (!$root) {
            return e(strip_tags($html));
        }
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, self::DROP, true)) {
                    $node->removeChild($child);
                    continue;
                }
                self::walk($child);
                if (!in_array($tag, self::ALLOWED, true)) {
                    // Keep the text, drop the element.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                while ($child->attributes->length) {
                    $child->removeAttribute($child->attributes->item(0)->nodeName);
                }
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }
}
