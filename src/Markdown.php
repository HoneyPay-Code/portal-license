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
        $inList = false;

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                if ($inCode) {
                    $html[] = '</code></pre>';
                    $inCode = false;
                } else {
                    if ($inList) {
                        $html[] = '</ul>';
                        $inList = false;
                    }
                    $html[] = '<pre class="code"><code>';
                    $inCode = true;
                }
                continue;
            }
            if ($inCode) {
                $html[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8')."\n";
                continue;
            }

            if (preg_match('/^###\s+(.+)$/', $line, $m)) {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $html[] = '<h3>'.self::inline($m[1]).'</h3>';
                continue;
            }
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $html[] = '<h2>'.self::inline($m[1]).'</h2>';
                continue;
            }
            if (preg_match('/^#\s+(.+)$/', $line, $m)) {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $html[] = '<h1>'.self::inline($m[1]).'</h1>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                if (! $inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }
                $html[] = '<li>'.self::inline($m[1]).'</li>';
                continue;
            }
            if (trim($line) === '') {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                continue;
            }
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            $html[] = '<p>'.self::inline($line).'</p>';
        }
        if ($inList) {
            $html[] = '</ul>';
        }
        if ($inCode) {
            $html[] = '</code></pre>';
        }

        return implode("\n", $html);
    }

    private static function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?: $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?: $escaped;

        return preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/',
            static function (array $m): string {
                $label = $m[1];
                $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
                if (! SafeUrl::isHttpUrl($url, true)) {
                    return $label;
                }
                $safeHref = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                return '<a href="'.$safeHref.'" rel="noopener noreferrer">'.$label.'</a>';
            },
            $escaped
        ) ?: $escaped;
    }
}
