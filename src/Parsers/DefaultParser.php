<?php

namespace Maiorano\Shortcodes\Parsers;

use Closure;
use Generator;

/**
 * Class DefaultParser.
 */
class DefaultParser implements ParserInterface
{
    /**
     * @param string       $content
     * @param array        $tags
     * @param Closure|null $callback
     *
     * @return array|string|string[]|null
     */
    public function parseShortcode(string $content, array $tags, Closure $callback = null)
    {
        if (strpos($content, '[') === false && empty($tags)) {
            return is_null($callback) ? [] : $content;
        }

        $regex = $this->getRegex($tags);

        preg_match_all("/$regex/", $content, $matches, PREG_SET_ORDER);

        if (is_null($callback)) {
            return iterator_to_array($this->generateResults($matches));
        }

        return preg_replace_callback("/$regex/", function ($match) use ($callback) {
            if ($match[1] == '[' && $match[6] == ']') {
                return substr($match[0], 1, -1);
            }

            $content = isset($match[5]) ? $match[5] : null;
            $atts = isset($match[3]) ? $this->parseAttributes($match[3]) : [];

            return $callback($match[2], $content, $atts);
        }, $content);
    }

    /**
     * @param array $tags
     *
     * @return string
     *
     * @see https://core.trac.wordpress.org/browser/tags/4.9/src/wp-includes/shortcodes.php#L228
     */
    private function getRegex(array $tags): string
    {
        $tagregexp = implode('|', array_map('preg_quote', $tags));

        return
            '\\['                // Opening bracket
            .'(\\[?)'           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
            ."($tagregexp)"     // 2: Shortcode name
            .'(?![\\w-])'       // Not followed by word character or hyphen
            .'('                // 3: Unroll the loop: Inside the opening shortcode tag
            .'[^\\]\\/]*'       // Not a closing bracket or forward slash
            .'(?:'
            .'\\/(?!\\])'       // A forward slash not followed by a closing bracket
            .'[^\\]\\/]*'       // Not a closing bracket or forward slash
            .')*?'
            .')'
            .'(?:'
            .'(\\/)'            // 4: Self closing tag ...
            .'\\]'              // ... and closing bracket
            .'|'
            .'\\]'              // Closing bracket
            .'(?:'
            .'('                // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
            .'[^\\[]*+'         // Not an opening bracket
            .'(?:'
            .'\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
            .'[^\\[]*+'         // Not an opening bracket
            .')*+'
            .')'
            .'\\[\\/\\2\\]'     // Closing shortcode tag
            .')?'
            .')'
            .'(\\]?)';          // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
    }

    /**
     * @param string $text
     *
     * @return array
     *
     * @see https://core.trac.wordpress.org/browser/tags/4.9/src/wp-includes/shortcodes.php#L482
     */
    public function parseAttributes(string $text): array
    {
        $atts = [];
        $patterns = implode('|', [
            '([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)', // attribute="value"
            '([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)', // attribute='value'
            '([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)', // attribute=value
            '"([^"]*)"(?:\s|$)', // "attribute"
            '\'([^\']*)\'(?:\s|$)', // 'attribute'
            '(\S+)(?:\s|$)', // attribute
        ]);
        $pattern = "/{$patterns}/";
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", ' ', $text);
        if (preg_match_all($pattern, (string) $text, $match, PREG_SET_ORDER)) {

            // Reject any unclosed HTML elements
            foreach ($this->generateAttributes($match) as $att => $value) {
                if (strpos($value, '<') !== false) {
                    if (preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value) !== 1) {
                        $value = '';
                    }
                }
                $atts[$att] = $value;
            }
        }

        return $atts;
    }

    /**
     * @param array $matches
     *
     * @return Generator
     */
    private function generateAttributes(array $matches): Generator
    {
        foreach ($matches as $m) {
            if (!empty($m[1])) {
                yield strtolower($m[1]) => stripcslashes($m[2]);
            } elseif (!empty($m[3])) {
                yield strtolower($m[3]) => stripcslashes($m[4]);
            } elseif (!empty($m[5])) {
                yield strtolower($m[5]) => stripcslashes($m[6]);
            } elseif (isset($m[7]) && strlen($m[7])) {
                yield strtolower($m[7]) => true;
            } elseif (isset($m[8]) && strlen($m[8])) {
                yield strtolower($m[8]) => true;
            } elseif (isset($m[9])) {
                yield strtolower($m[9]) => true;
            }
        }
    }

    /**
     * @param array $matches
     *
     * @return Generator
     */
    private function generateResults(array $matches): Generator
    {
        foreach ($matches as $match) {
            if ($match[1] == '[' && $match[6] == ']') {
                continue;
            }
            yield [
                'tag'        => $match[2],
                'content'    => isset($match[5]) ? $match[5] : null,
                'attributes' => isset($match[3]) ? $this->parseAttributes($match[3]) : [],
            ];
        }
    }
}
