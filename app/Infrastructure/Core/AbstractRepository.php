<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Infrastructure\Core;

use App\Infrastructure\Core\DataIsolation\BaseDataIsolation;
use App\Infrastructure\Core\DataIsolation\DataIsolationFilter;
use App\Infrastructure\Core\ValueObject\Page;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;

abstract class AbstractRepository
{
    use DataIsolationFilter;

    protected string $filterOrganizationCodeAlias = 'organization_code';

    protected string $filterEnvironmentAlias = 'environment';

    protected bool $filterOrganizationCode = false;

    protected bool $filterEnvironment = false;

    protected array $attributeMaps = [
    ];

    protected function createBuilder(BaseDataIsolation $dataIsolation, Builder $builder): Builder
    {
        if ($this->filterOrganizationCode) {
            $this->addIsolationOrganizationCodeFilter($builder, $dataIsolation, $this->filterOrganizationCodeAlias);
        }
        if ($this->filterEnvironment) {
            $this->addIsolationEnvironment($builder, $dataIsolation, $this->filterEnvironmentAlias);
        }
        return $builder;
    }

    /**
     * @return array{total: int, list: array|Collection}
     */
    protected function getByPage(Builder $builder, Page $page, ?AbstractQuery $query = null): array
    {
        if ($query) {
            foreach ($query->getOrder() as $column => $order) {
                $builder->orderBy($column, $order);
            }
            if (! empty($query->getSelect())) {
                $builder->select($query->getSelect());
            }
        }
        if (! $page->isEnabled()) {
            $list = $builder->get();
            return [
                'total' => count($list),
                'list' => $list,
            ];
        }
        $total = -1;
        if ($page->isTotal()) {
            $total = $builder->count();
        }
        $list = [];
        if (! $page->isTotal() || $total > 0) {
            $list = $builder->forPage($page->getPage(), $page->getPageNum())->get();
        }
        return [
            'total' => $total,
            'list' => $list,
        ];
    }

    protected function getAttributes(AbstractEntity $entity): array
    {
        $attributes = [];
        $array = $entity->toArray();
        foreach ($array as $key => $value) {
            if (array_key_exists($key, $this->attributeMaps)) {
                $attributes[$this->attributeMaps[$key]] = $value;
            } else {
                $attributes[$key] = $value;
            }
        }

        if (empty($attributes['id'])) {
            unset($attributes['id']);
        }
        return $attributes;
    }
}
