<?php

declare(strict_types=1);

namespace LicenseApi;

final class Markdown
{
    public static function toHtml(string $markdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $text);
        $html = [];
        $inCode = false;
        $listType = null; // ul|ol
        $inBlockquote = false;
        $tableRows = [];

        $closeList = static function () use (&$html, &$listType): void {
            if ($listType === 'ul') {
                $html[] = '</ul>';
            } elseif ($listType === 'ol') {
                $html[] = '</ol>';
            }
            $listType = null;
        };

        $closeQuote = static function () use (&$html, &$inBlockquote): void {
            if ($inBlockquote) {
                $html[] = '</div>';
                $inBlockquote = false;
            }
        };

        $flushTable = static function () use (&$html, &$tableRows): void {
            if ($tableRows === []) {
                return;
            }
            $html[] = '<div class="docs-table-wrap"><table class="docs-table">';
            foreach ($tableRows as $i => $cells) {
                $tag = $i === 0 ? 'th' : 'td';
                if ($i === 1 && self::isTableSeparator($cells)) {
                    continue;
                }
                $html[] = '<tr>';
                foreach ($cells as $cell) {
                    $html[] = '<'.$tag.'>'.self::inline(trim($cell)).'</'.$tag.'>';
                }
                $html[] = '</tr>';
            }
            $html[] = '</table></div>';
            $tableRows = [];
        };

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                $flushTable();
                $closeList();
                $closeQuote();
                if ($inCode) {
                    $html[] = '</code></pre>';
                    $inCode = false;
                } else {
                    $html[] = '<pre class="code"><code>';
                    $inCode = true;
                }
                continue;
            }
            if ($inCode) {
                $html[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8')."\n";
                continue;
            }

            // Table row
            if (str_contains($line, '|') && preg_match('/^\s*\|(.+)\|\s*$/', $line, $tm)) {
                $closeList();
                $closeQuote();
                $cells = array_map('trim', explode('|', trim($tm[1])));
                $tableRows[] = $cells;
                continue;
            }
            if ($tableRows !== []) {
                $flushTable();
            }

            if (trim($line) === '---' || trim($line) === '***' || trim($line) === '___') {
                $closeList();
                $closeQuote();
                $html[] = '<hr>';
                continue;
            }

            // Blockquote / callouts
            if (preg_match('/^>\s?(.*)$/', $line, $qm)) {
                $closeList();
                $inner = $qm[1];
                if (! $inBlockquote) {
                    $class = 'docs-callout';
                    if (preg_match('/^\*\*(Dica|Nota|Info)\*\*/i', $inner)) {
                        $class .= ' docs-callout-tip';
                    } elseif (preg_match('/^\*\*(Atenção|Aviso|Importante|Cuidado)\*\*/i', $inner)) {
                        $class .= ' docs-callout-warn';
                    }
                    $html[] = '<div class="'.$class.'">';
                    $inBlockquote = true;
                }
                if (trim($inner) === '') {
                    continue;
                }
                $html[] = '<p>'.self::inline($inner).'</p>';
                continue;
            }
            if ($inBlockquote) {
                $closeQuote();
            }

            if (preg_match('/^###\s+(.+)$/', $line, $m)) {
                $closeList();
                $html[] = '<h3 id="'.self::slugify($m[1]).'">'.self::inline($m[1]).'</h3>';
                continue;
            }
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                $closeList();
                $html[] = '<h2 id="'.self::slugify($m[1]).'">'.self::inline($m[1]).'</h2>';
                continue;
            }
            if (preg_match('/^#\s+(.+)$/', $line, $m)) {
                $closeList();
                $html[] = '<h1 id="'.self::slugify($m[1]).'">'.self::inline($m[1]).'</h1>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                if ($listType !== 'ul') {
                    $closeList();
                    $html[] = '<ul>';
                    $listType = 'ul';
                }
                $html[] = '<li>'.self::inline($m[1]).'</li>';
                continue;
            }
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                if ($listType !== 'ol') {
                    $closeList();
                    $html[] = '<ol>';
                    $listType = 'ol';
                }
                $html[] = '<li>'.self::inline($m[1]).'</li>';
                continue;
            }
            if (trim($line) === '') {
                $closeList();
                continue;
            }
            $closeList();
            $html[] = '<p>'.self::inline($line).'</p>';
        }

        $flushTable();
        $closeList();
        $closeQuote();
        if ($inCode) {
            $html[] = '</code></pre>';
        }

        return implode("\n", $html);
    }

    /**
     * @return list<array{id:string,text:string,level:int}>
     */
    public static function extractToc(string $html): array
    {
        $toc = [];
        if (preg_match_all('/<h([23])\s+id="([^"]+)">(.+?)<\/h\1>/u', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $toc[] = [
                    'level' => (int) $row[1],
                    'id' => $row[2],
                    'text' => trim(strip_tags($row[3])),
                ];
            }
        }

        return $toc;
    }

    public static function slugify(string $text): string
    {
        $t = mb_strtolower(trim(strip_tags($text)), 'UTF-8');
        $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
        $t = strtr($t, $map);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?: '';

        return trim($t, '-') ?: 'section';
    }

    /** @param list<string> $cells */
    private static function isTableSeparator(array $cells): bool
    {
        foreach ($cells as $c) {
            if (! preg_match('/^:?-+:?$/', trim($c))) {
                return false;
            }
        }

        return $cells !== [];
    }

    private static function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?: $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?: $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $escaped) ?: $escaped;

        $escaped = preg_replace_callback(
            '/!\[([^\]]*)\]\((https?:\/\/[^)\s]+)\)/',
            static function (array $m): string {
                $alt = $m[1];
                $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
                if (! SafeUrl::isHttpUrl($url, true)) {
                    return $alt;
                }
                $safeHref = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                return '<img src="'.$safeHref.'" alt="'.$alt.'" loading="lazy" class="docs-img">';
            },
            $escaped
        ) ?: $escaped;

        return preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]*)\)/',
            static function (array $m): string {
                $label = $m[1];
                $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
                if (str_starts_with($url, '/')) {
                    $safeHref = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                    return '<a href="'.$safeHref.'">'.$label.'</a>';
                }
                if (! SafeUrl::isHttpUrl($url, true)) {
                    return $label;
                }
                $safeHref = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                return '<a href="'.$safeHref.'" rel="noopener noreferrer" target="_blank">'.$label.'</a>';
            },
            $escaped
        ) ?: $escaped;
    }
}
