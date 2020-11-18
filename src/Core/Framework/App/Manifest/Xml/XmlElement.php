<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Struct\Struct;

class XmlElement extends Struct
{
    private const FALLBACK_LOCALE = 'en-GB';

    public function toArray(string $defaultLocale): array
    {
        $array = get_object_vars($this);

        unset($array['extensions']);

        return $array;
    }

    protected static function mapTranslatedTag(\DOMElement $child, array $values): array
    {
        if (!array_key_exists($child->tagName, $values)) {
            $values[self::snakeCaseToCamelCase($child->tagName)] = [];
        }

        // psalm would fail if it can't infer type from nested array
        /** @var array<string, string> $tagValues */
        $tagValues = $values[self::snakeCaseToCamelCase($child->tagName)];
        $tagValues[self::getLocaleCodeFromElement($child)] = $child->nodeValue;
        $values[self::snakeCaseToCamelCase($child->tagName)] = $tagValues;

        return $values;
    }

    protected static function parseChildNodes(\DOMElement $child, callable $transformer): array
    {
        $values = [];
        foreach ($child->childNodes as $field) {
            if (!$field instanceof \DOMElement) {
                continue;
            }

            $values[] = $transformer($field);
        }

        return $values;
    }

    protected static function snakeCaseToCamelCase(string $string): string
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * if translations for system default language are not provided it tries to use the english translation as the default,
     * if english does not exist it uses the first translation
     */
    protected function ensureTranslationForDefaultLanguageExist(array $translations, string $defaultLocale): array
    {
        if (empty($translations)) {
            return $translations;
        }

        if (!array_key_exists($defaultLocale, $translations)) {
            $translations[$defaultLocale] = $this->getFallbackTranslation($translations);
        }

        return $translations;
    }

    private static function getLocaleCodeFromElement(\DOMElement $element): string
    {
        return $element->getAttribute('lang') ?: self::FALLBACK_LOCALE;
    }

    private function getFallbackTranslation(array $translations): string
    {
        if (array_key_exists(self::FALLBACK_LOCALE, $translations)) {
            return $translations[self::FALLBACK_LOCALE];
        }

        return array_values($translations)[0];
    }
}
