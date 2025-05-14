<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\Contact\Repository\Persistence;

use App\Domain\Chat\DTO\PageResponseDTO\DepartmentsPageResponseDTO;
use App\Domain\Chat\Entity\ValueObject\PlatformRootDepartmentId;
use App\Domain\Contact\Entity\MagicDepartmentEntity;
use App\Domain\Contact\Entity\ValueObject\DepartmentOption;
use App\Domain\Contact\Repository\Facade\MagicDepartmentRepositoryInterface;
use App\Domain\Contact\Repository\Persistence\Model\DepartmentModel;
use App\Infrastructure\Util\IdGenerator\IdGenerator;
use App\Interfaces\Chat\Assembler\DepartmentAssembler;
use Hyperf\Cache\Annotation\Cacheable;
use Hyperf\Database\Model\Builder;
use Hyperf\DbConnection\Db;
use JetBrains\PhpStorm\ArrayShape;

class MagicDepartmentRepository implements MagicDepartmentRepositoryInterface
{
    public function __construct(
        protected DepartmentModel $model,
    ) {
    }

    public function getDepartmentById(string $departmentId, string $organizationCode): ?MagicDepartmentEntity
    {
        $department = $this->model->newQuery()
            ->where('organization_code', $organizationCode)
            ->where('department_id', $departmentId);
        $department = Db::select($department->toSql(), $department->getBindings())[0] ?? null;
        if (empty($department)) {
            return null;
        }
        return DepartmentAssembler::getDepartmentEntity($department);
    }

    /**
     * @return MagicDepartmentEntity[]
     */
    public function getDepartmentsByIds(array $departmentIds, string $organizationCode, bool $keyById = false): array
    {
        if (empty($departmentIds)) {
            return [];
        }
        $departments = $this->model->newQuery()
            ->where('organization_code', $organizationCode)
            ->whereIn('department_id', $departmentIds);
        $departments = Db::select($departments->toSql(), $departments->getBindings());
        return $this->getDepartmentsEntity($departments, $keyById);
    }

    /**
     * @return MagicDepartmentEntity[]
     */
    public function getDepartmentsByIdsInMagic(array $departmentIds, bool $keyById = false): array
    {
        if (empty($departmentIds)) {
            return [];
        }
        $departments = $this->model->newQuery()
            ->whereIn('department_id', $departmentIds);
        $departments = Db::select($departments->toSql(), $departments->getBindings());
        return $this->getDepartmentsEntity($departments, $keyById);
    }

    public function getSubDepartmentsById(string $departmentId, string $organizationCode, int $size, int $offset): DepartmentsPageResponseDTO
    {
        $departments = $this->model->newQuery()
            ->where('organization_code', $organizationCode)
            ->where('parent_department_id', $departmentId)
            ->limit($size)
            ->offset($offset)
            ->orderBy('id');
        $departments = Db::select($departments->toSql(), $departments->getBindings());
        $items = $this->getDepartmentsEntity($departments);
        $hasMore = count($items) === $size;
        $pageToken = $hasMore ? (string) ($offset + $size) : '';
        return new DepartmentsPageResponseDTO([
            'items' => $items,
            'has_more' => $hasMore,
            'page_token' => $pageToken,
        ]);
    }

    /**
     * 获取某一层级的部门.
     */
    public function getSubDepartmentsByLevel(int $level, string $organizationCode, int $depth, int $size, int $offset): DepartmentsPageResponseDTO
    {
        $departments = $this->getSubDepartmentsByLevelCache($level, $organizationCode, $depth, $size, $offset);
        $magicDepartmentEntities = $this->getDepartmentsEntity($departments);
        // 下一级子部门有不可预测的数量，因此只要返回数量与limit一致，就认为有下一页
        $hasMore = count($magicDepartmentEntities) === $size;
        $pageToken = $hasMore ? (string) ($offset + $size) : '';
        return new DepartmentsPageResponseDTO([
            'items' => $magicDepartmentEntities,
            'has_more' => $hasMore,
            'page_token' => $pageToken,
        ]);
    }

    // 给定的部门id是否有下级部门
    #[ArrayShape([
        'parent_department_id' => 'string',
    ])]
    public function hasChildDepartment(array $departmentIds, string $organizationCode): array
    {
        $query = $this->model->newQuery()
            ->select('parent_department_id')
            ->where('organization_code', $organizationCode)
            ->whereIn('parent_department_id', $departmentIds)
            ->groupBy(['parent_department_id']);
        return Db::select($query->toSql(), $query->getBindings());
    }

    public function getDepartmentByParentId(string $departmentId, string $organizationCode): ?MagicDepartmentEntity
    {
        // 对于前端来说, -1 表示根部门信息.
        $query = $this->model->newQuery()->where('organization_code', $organizationCode);
        if ($departmentId === PlatformRootDepartmentId::Magic) {
            $query->where(function (Builder $query) {
                $query->where('parent_department_id', '=', '')->orWhereNull('parent_department_id');
            });
        } else {
            $query->whereIn('parent_department_id', $departmentId);
        }
        $department = Db::select($query->toSql(), $query->getBindings())[0] ?? null;
        if (empty($department)) {
            return null;
        }
        return DepartmentAssembler::getDepartmentEntity($department);
    }

    /**
     * @return MagicDepartmentEntity[]
     */
    public function searchDepartments(string $departmentName, string $organizationCode, string $pageToken = '', ?int $pageSize = null): array
    {
        $departments = $this->model->newQuery()
            ->where('organization_code', $organizationCode)
            ->where('name', 'like', "%{$departmentName}%")
            ->limit(100)
            /* @phpstan-ignore-next-line */
            ->when($pageSize, function (Builder $query) use ($pageToken, $pageSize) {
                // $pageToken 为查询总量
                $page = ((int) ceil((int) $pageToken / $pageSize)) + 1;
                $query->forPage($page, $pageSize);
            });
        $departments = Db::select($departments->toSql(), $departments->getBindings());
        return $this->getDepartmentsEntity($departments);
    }

    /**
     * 获取组织的所有部门.
     * @return MagicDepartmentEntity[]
     */
    public function getOrganizationDepartments(string $organizationCode, array $fields = ['*'], bool $keyById = false): array
    {
        $departments = $this->getOrganizationDepartmentsRaw($organizationCode);
        return $this->getDepartmentsEntity($departments, $keyById);
    }

    public function addDepartmentDocument(string $departmentId, string $documentId): void
    {
        $this->model->newQuery()->where('department_id', $departmentId)->update(['document_id' => $documentId]);
    }

    /**
     * 获取部门的所有子部门的成员总数.
     */
    public function getSelfAndChildrenEmployeeSum(MagicDepartmentEntity $magicDepartmentEntity): int
    {
        $departments = $this->getChildrenDepartmentsFromCache($magicDepartmentEntity->getOrganizationCode(), $magicDepartmentEntity->getLevel());
        $employeeSum = 0;
        foreach ($departments as $department) {
            if (str_contains($department['path'], $magicDepartmentEntity->getDepartmentId())) {
                $employeeSum += $department['employee_sum'];
            }
        }
        return $employeeSum;
    }

    /**
     * @param MagicDepartmentEntity[] $magicDepartmentsDTO
     * @return MagicDepartmentEntity[]
     */
    public function createDepartments(array $magicDepartmentsDTO): array
    {
        $time = date('Y-m-d H:i:s');
        $departments = [];
        foreach ($magicDepartmentsDTO as $magicDepartmentDTO) {
            if (empty($magicDepartmentDTO->getId())) {
                $department['id'] = (string) IdGenerator::getSnowId();
                $magicDepartmentDTO->setId($department['id']);
            }
            if (empty($magicDepartmentDTO->getCreatedAt())) {
                $magicDepartmentDTO->setCreatedAt($time);
            }
            if (empty($magicDepartmentDTO->getUpdatedAt())) {
                $magicDepartmentDTO->setUpdatedAt($time);
            }
            $department = $magicDepartmentDTO->toArray();
            unset($department['has_child']);
            $departments[] = $department;
        }
        $this->model::query()->insert($departments);
        return $magicDepartmentsDTO;
    }

    public function updateDepartment(string $departmentId, array $data, string $organizationCode): int
    {
        unset($data['has_child']);
        return $this->model->newQuery()
            ->where('department_id', $departmentId)
            ->where('organization_code', $organizationCode)
            ->update($data);
    }

    public function updateDepartmentsOptionByIds(array $departmentIds, ?DepartmentOption $departmentOption = null): int
    {
        if (empty($departmentIds)) {
            return 0;
        }
        $data['option'] = $departmentOption?->value;
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->model->newQuery()->whereIn('department_id', $departmentIds)->update($data);
    }

    public function getDepartmentRootId(string $organizationCode): ?string
    {
        $department = $this->model->newQuery()
            ->where('organization_code', $organizationCode)
            ->where('department_id', '=', PlatformRootDepartmentId::Magic)
            ->first();

        return $department?->department_id;
    }

    /**
     * 批量获取多个组织的根部门信息.
     * @param array $organizationCodes 组织代码数组
     * @return MagicDepartmentEntity[] 根部门实体数组
     */
    public function getOrganizationsRootDepartment(array $organizationCodes): array
    {
        if (empty($organizationCodes)) {
            return [];
        }

        $departments = $this->model->newQuery()
            ->whereIn('organization_code', $organizationCodes)
            ->where('department_id', '=', PlatformRootDepartmentId::Magic);

        $departments = Db::select($departments->toSql(), $departments->getBindings());
        return $this->getDepartmentsEntity($departments);
    }

    /**
     * @return MagicDepartmentEntity[]
     */
    protected function getDepartmentsEntity(array $departments, bool $keyById = false): array
    {
        $departmentsEntity = [];
        foreach ($departments as $department) {
            $departmentEntity = DepartmentAssembler::getDepartmentEntity($department);
            if ($keyById) {
                $departmentsEntity[$departmentEntity->getDepartmentId()] = $departmentEntity;
            } else {
                $departmentsEntity[] = $departmentEntity;
            }
        }
        return $departmentsEntity;
    }

    #[Cacheable(prefix: 'get_organization_departments_raw', value: '#{organizationCode}', ttl: 60)]
    private function getOrganizationDepartmentsRaw(string $organizationCode): array
    {
        $departments = $this->model->newQuery()->where('organization_code', $organizationCode);
        return Db::select($departments->toSql(), $departments->getBindings());
    }

    #[Cacheable(prefix: 'getSubDepartmentsByLevel', ttl: 60)]
    private function getSubDepartmentsByLevelCache(int $currentDepartmentLevel, string $organizationCode, int $depth, int $size, int $offset): array
    {
        $minDepth = $currentDepartmentLevel + 1;
        $maxDepth = $currentDepartmentLevel + $depth;
        if ($minDepth > $maxDepth) {
            return [];
        }
        $query = $this->model->newQuery()
            ->where('organization_code', $organizationCode);
        if ($minDepth === $maxDepth) {
            $query->where('level', $minDepth);
        } else {
            $query->whereBetween('level', [$minDepth, $maxDepth]);
        }
        $query->limit($size)->offset($offset)->orderBy('id');
        return Db::select($query->toSql(), $query->getBindings());
    }

    #[Cacheable(prefix: 'getChildrenDepartments', ttl: 60)]
    private function getChildrenDepartmentsFromCache(string $orgCode, int $level): array
    {
        $query = $this->model->newQuery()
            ->where('organization_code', $orgCode)
            ->where('level', '>=', $level);
        return Db::select($query->toSql(), $query->getBindings());
    }
}
