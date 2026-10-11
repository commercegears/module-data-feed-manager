<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute as Attribute;
use Magento\Eav\Model\Config as EavConfig;

/**
 * Turns stored attribute values into store-view display values (option labels, Yes/No, numbers).
 */
class AttributeResolver
{
    /** @var array<string, Attribute|false> */
    private array $attributes = [];

    /** @var array<string, array<string, string>> */
    private array $options = [];

    /** @var string[]|null */
    private ?array $allCodes = null;

    public function __construct(private readonly EavConfig $eavConfig)
    {
    }

    public function getAttribute(string $code): ?Attribute
    {
        if (!array_key_exists($code, $this->attributes)) {
            try {
                $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
                $this->attributes[$code] = $attribute && $attribute->getId() ? $attribute : false;
            } catch (\Exception) {
                $this->attributes[$code] = false;
            }
        }
        return $this->attributes[$code] ?: null;
    }

    public function isAttribute(string $code): bool
    {
        return in_array($code, $this->getAllCodes(), true);
    }

    /**
     * @return string[]
     */
    public function getAllCodes(): array
    {
        if ($this->allCodes === null) {
            $this->allCodes = array_map(
                'strval',
                array_keys($this->eavConfig->getEntityAttributes(Product::ENTITY))
            );
        }
        return $this->allCodes;
    }

    /**
     * Display value for the store view: labels for select/multiselect/boolean, floats for prices and decimals.
     */
    public function getDisplayValue(string $code, mixed $raw, int $storeId): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $attribute = $this->getAttribute($code);
        if ($attribute === null) {
            return $raw;
        }
        $input = (string)$attribute->getFrontendInput();
        if (in_array($input, ['select', 'multiselect', 'boolean'], true)
            || ($attribute->getSourceModel() && $input !== 'text')
        ) {
            $options = $this->getOptions($attribute, $storeId);
            if ($input === 'multiselect') {
                $labels = [];
                foreach (explode(',', (string)$raw) as $value) {
                    $labels[] = $options[$value] ?? $value;
                }
                return implode(', ', $labels);
            }
            if (is_scalar($raw) && isset($options[(string)$raw])) {
                return $options[(string)$raw];
            }
            return $raw;
        }
        if ($input === 'price' || $attribute->getBackendType() === 'decimal') {
            return is_numeric($raw) ? (float)$raw : $raw;
        }
        if ($attribute->getBackendType() === 'int' && is_numeric($raw)) {
            return (int)$raw;
        }
        return $raw;
    }

    /**
     * Converts a label to its option ID for select/multiselect attributes (used by filter conditions).
     */
    public function getOptionId(string $code, string $label, int $storeId): ?string
    {
        $attribute = $this->getAttribute($code);
        if ($attribute === null || !$attribute->usesSource()) {
            return null;
        }
        foreach ($this->getOptions($attribute, $storeId) as $value => $text) {
            if ((string)$value === $label || mb_strtolower($text) === mb_strtolower($label)) {
                return (string)$value;
            }
        }
        foreach ($this->getOptions($attribute, 0) as $value => $text) {
            if (mb_strtolower($text) === mb_strtolower($label)) {
                return (string)$value;
            }
        }
        return null;
    }

    /**
     * @return array<string, string>
     */
    private function getOptions(Attribute $attribute, int $storeId): array
    {
        $key = $attribute->getAttributeCode() . '|' . $storeId;
        if (!isset($this->options[$key])) {
            $this->options[$key] = [];
            try {
                $attribute->setStoreId($storeId);
                foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                    if (!is_array($option) || !isset($option['value']) || is_array($option['value'])) {
                        continue;
                    }
                    $this->options[$key][(string)$option['value']] = (string)($option['label'] ?? '');
                }
            } catch (\Throwable) {
                $this->options[$key] = [];
            }
        }
        return $this->options[$key];
    }
}
