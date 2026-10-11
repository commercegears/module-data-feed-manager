<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Currencies allowed in any store view, plus "store view default".
 */
class Currency implements OptionSourceInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CurrencyInterface $localeCurrency
    ) {
    }

    public function toOptionArray(): array
    {
        $codes = [];
        foreach ($this->storeManager->getStores() as $store) {
            foreach ((array)$store->getAvailableCurrencyCodes(true) as $code) {
                $codes[$code] = $code;
            }
            $codes[$store->getBaseCurrencyCode()] = $store->getBaseCurrencyCode();
        }
        ksort($codes);
        $result = [['value' => '', 'label' => __('Store view default')]];
        foreach ($codes as $code) {
            try {
                $name = (string)$this->localeCurrency->getCurrency($code)->getName();
            } catch (\Throwable) {
                $name = '';
            }
            $result[] = ['value' => $code, 'label' => $name !== '' ? $code . ' - ' . $name : $code];
        }
        return $result;
    }
}
