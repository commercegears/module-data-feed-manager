<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Block\Adminhtml\Mapping;

use Magento\Backend\Block\Template;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Softaware\DataFeed\Model\ResourceModel\CategoryMap;
use Softaware\DataFeed\Model\ResourceModel\Taxonomy;

/**
 * Category tree (all root categories) with the mapped Google category per row.
 */
class Tree extends Template
{
    /** @var array<int, int>|null */
    private ?array $map = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly CategoryMap $categoryMap,
        private readonly Taxonomy $taxonomy,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Categories in tree order.
     *
     * @return array<int, array{id: int, name: string, level: int, path_ids: int[], active: bool, value: string, inherited: string}>
     */
    public function getRows(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId(0)
            ->addAttributeToSelect(['name', 'is_active'])
            ->addFieldToFilter('level', ['gt' => 0])
            ->setOrder('path', 'ASC');

        $categories = [];
        foreach ($collection as $category) {
            $categories[(int)$category->getId()] = [
                'id' => (int)$category->getId(),
                'name' => (string)$category->getName(),
                'level' => (int)$category->getLevel(),
                'path_ids' => array_map('intval', explode('/', (string)$category->getPath())),
                'position' => (int)$category->getPosition(),
                'active' => (bool)$category->getIsActive(),
            ];
        }
        // Order by position within each parent, depth first.
        $children = [];
        foreach ($categories as $id => $category) {
            $parentId = $category['path_ids'][count($category['path_ids']) - 2] ?? 0;
            $children[$parentId][] = $id;
        }
        foreach ($children as &$list) {
            usort($list, static fn ($a, $b) => [$categories[$a]['position'], $a] <=> [$categories[$b]['position'], $b]);
        }
        unset($list);

        $map = $this->getMap();
        $paths = $this->taxonomy->getPaths(array_values(array_unique($map)));
        $rows = [];
        $walk = function (int $parentId) use (&$walk, &$rows, $children, $categories, $map, $paths): void {
            foreach ($children[$parentId] ?? [] as $id) {
                $category = $categories[$id];
                $value = isset($map[$id]) ? $map[$id] . ' - ' . ($paths[$map[$id]] ?? '') : '';
                $inherited = '';
                if ($value === '') {
                    for ($i = count($category['path_ids']) - 2; $i >= 0; $i--) {
                        $ancestor = $category['path_ids'][$i];
                        if (isset($map[$ancestor])) {
                            $inherited = $map[$ancestor] . ' - ' . ($paths[$map[$ancestor]] ?? '');
                            break;
                        }
                    }
                }
                $rows[] = $category + ['value' => $value, 'inherited' => $inherited];
                $walk($id);
            }
        };
        $walk(1);
        return $rows;
    }

    public function getTaxonomyCount(): int
    {
        return $this->taxonomy->count();
    }

    public function getMappedCount(): int
    {
        return count($this->getMap());
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save');
    }

    public function getSearchUrl(): string
    {
        return $this->getUrl('*/*/search');
    }

    public function getImportUrl(): string
    {
        return $this->getUrl('*/*/import');
    }

    /**
     * @return array<int, int>
     */
    private function getMap(): array
    {
        if ($this->map === null) {
            $this->map = $this->categoryMap->getMap();
        }
        return $this->map;
    }
}
