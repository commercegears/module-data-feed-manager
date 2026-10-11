<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Option list configured in di.xml (value => label).
 */
class StaticOptions implements OptionSourceInterface
{
    /**
     * @param array<string, string> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function toOptionArray(): array
    {
        $result = [];
        foreach ($this->options as $value => $label) {
            $result[] = ['value' => (string)$value, 'label' => __($label)];
        }
        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->options as $value => $label) {
            $result[(string)$value] = (string)__($label);
        }
        return $result;
    }
}
