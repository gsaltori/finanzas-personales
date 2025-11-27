<?php
// app/Services/ParserService.php
namespace App\Services;

class ParserService
{
    public function sanitizeHtml(string $html): string
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();

        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        $ok = $doc->loadHTML("<div>" . $html . "</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$ok) {
            libxml_clear_errors();
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $xpath = new \DOMXPath($doc);

        foreach ($xpath->query('//script|//style') as $node) {
            $node->parentNode->removeChild($node);
        }

        foreach ($xpath->query('//*') as $node) {
            if ($node->hasAttributes()) {
                $attrs = [];
                foreach ($node->attributes as $attr) {
                    $attrs[] = $attr->name;
                }
                foreach ($attrs as $attrName) {
                    if (stripos($attrName, 'on') === 0) {
                        $node->removeAttribute($attrName);
                        continue;
                    }
                    if (in_array(strtolower($attrName), ['href', 'src', 'xlink:href'])) {
                        $val = $node->getAttribute($attrName);
                        if (preg_match('#^[\s]*javascript:#i', $val)) {
                            $node->removeAttribute($attrName);
                        }
                    }
                }
            }
        }

        foreach ($xpath->query('//@style') as $styleAttr) {
            $styleAttr->ownerElement->removeAttributeNode($styleAttr);
        }

        $container = $doc->getElementsByTagName('div')->item(0);
        $result = '';
        if ($container) {
            foreach ($container->childNodes as $child) {
                $result .= $doc->saveHTML($child);
            }
        }

        libxml_clear_errors();
        return $result;
    }
}
