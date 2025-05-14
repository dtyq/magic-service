<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\Contact\Service;

use App\Domain\Authentication\DTO\LoginResponseDTO;
use App\Domain\Chat\Entity\MagicFriendEntity;
use App\Domain\Chat\Entity\ValueObject\FriendStatus;
use App\Domain\Contact\DTO\FriendQueryDTO;
use App\Domain\Contact\Entity\AccountEntity;
use App\Domain\Contact\Entity\MagicUserEntity;
use App\Domain\Contact\Entity\ValueObject\DataIsolation;
use App\Domain\Contact\Entity\ValueObject\PlatformType;
use App\Domain\Contact\Entity\ValueObject\UserOption;
use App\Domain\Contact\Entity\ValueObject\UserType;
use App\Domain\OrganizationEnvironment\Entity\MagicEnvironmentEntity;
use App\Domain\Token\Entity\MagicTokenEntity;
use App\Domain\Token\Entity\ValueObject\MagicTokenType;
use App\ErrorCode\ChatErrorCode;
use App\ErrorCode\MagicAccountErrorCode;
use App\ErrorCode\UserErrorCode;
use App\Infrastructure\Core\Exception\ExceptionBuilder;
use App\Infrastructure\Core\Traits\DataIsolationTrait;
use App\Infrastructure\Util\IdGenerator\IdGenerator;
use App\Interfaces\Chat\Assembler\UserAssembler;
use App\Interfaces\Chat\DTO\UserDetailDTO;
use Hyperf\Codec\Json;
use Throwable;

class MagicUserDomainService extends AbstractContactDomainService
{
    use DataIsolationTrait;

    /**
     * @throws Throwable
     */
    public function addFriend(DataIsolation $dataIsolation, string $friendId): bool
    {
        // 检查 uid 和 friendId 是否存在
        $uid = $dataIsolation->getCurrentUserId();
        $usersInfo = $this->userRepository->getUserByIdsAndOrganizations([$uid, $friendId]);
        $usersInfo = array_column($usersInfo, null, 'user_id');
        if (! isset($usersInfo[$uid], $usersInfo[$friendId])) {
            ExceptionBuilder::throw(UserErrorCode::USER_NOT_EXIST);
        }
        // 检测是否已经是好友
        if ($this->friendRepository->isFriend($uid, $friendId)) {
            return true;
        }
        /** @var MagicUserEntity $friendUserInfo */
        $friendUserInfo = $usersInfo[$friendId];
        $friendStatus = FriendStatus::Apply;
        if ($friendUserInfo->getUserType() === UserType::Ai) {
            // 如果是 ai ,直接同意
            $friendStatus = FriendStatus::Agree;
        } else {
            // 如果是人类，检查他们是否处于同一组织
            $this->assertUserInOrganization($friendId, $dataIsolation->getCurrentOrganizationCode());
        }
        // 将好友关系写入 friend 表.
        $this->friendRepository->insertFriend([
            'id' => IdGenerator::getSnowId(),
            'user_id' => $uid,
            'user_organization_code' => $dataIsolation->getCurrentOrganizationCode(),
            'friend_id' => $friendId,
            'friend_organization_code' => $usersInfo[$friendId]['organization_code'],
            'friend_type' => $friendUserInfo->getUserType(),
            'status' => $friendStatus->value,
            'remarks' => '',
            'extra' => '',
        ]);
        return true;
    }

    /**
     * 检查当前用户是否在当前组织内,并且账号是已激活状态
     */
    public function assertUserInOrganization(string $userId, string $currentOrganizationCode): void
    {
        $userOrganizations = $this->userRepository->getUserOrganizations($userId);
        if (! in_array($currentOrganizationCode, $userOrganizations, true)) {
            ExceptionBuilder::throw(UserErrorCode::ORGANIZATION_NOT_EXIST);
        }
    }

    public function getByUserId(string $uid): ?MagicUserEntity
    {
        return $this->userRepository->getUserById($uid);
    }

    /**
     * @return array<MagicUserEntity>
     */
    public function getUserByIds(array $ids, DataIsolation $dataIsolation, array $column = ['*']): array
    {
        return $this->userRepository->getUserByIdsAndOrganizations($ids, [$dataIsolation->getCurrentOrganizationCode()], $column);
    }

    public function getUserByPageToken(string $pageToken = '', int $pageSize = 50): array
    {
        return $this->userRepository->getUserByPageToken($pageToken, $pageSize);
    }

    /**
     * @return array<string, MagicUserEntity>
     */
    public function getByUserIds(DataIsolation $dataIsolation, array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));
        return $this->userRepository->getByUserIds($dataIsolation->getCurrentOrganizationCode(), $userIds);
    }

    public function searchFriend(string $keyword): array
    {
        // 检查 uid 和 friendId 是否存在
        [$popular, $latest] = $this->userRepository->searchByKeyword($keyword);
        // 按最受欢迎和最新加入各取前三
        return $this->getAgents($popular, $latest);
    }

    public function getAgentList(): array
    {
        [$popular, $latest] = $this->userRepository->getSquareAgentList();
        return $this->getAgents($popular, $latest);
    }

    public function getUserById(string $userId): ?MagicUserEntity
    {
        return $this->userRepository->getUserById($userId);
    }

    public function getByAiCode(DataIsolation $dataIsolation, string $aiCode): ?MagicUserEntity
    {
        $account = $this->accountRepository->getByAiCode($aiCode);
        if (! $account) {
            return null;
        }
        return $this->userRepository->getUserByMagicId($dataIsolation, $account->getMagicId());
    }

    /**
     * @return array<UserDetailDTO>
     */
    public function getUserDetailByUserIds(array $userIds, DataIsolation $dataIsolation): array
    {
        $userDetails = $this->getUserDetailByUserIdsInMagic($userIds);
        // 按组织过滤用户
        return array_filter($userDetails, static fn ($user) => $user->getOrganizationCode() === $dataIsolation->getCurrentOrganizationCode());
    }

    /**
     * 按昵称搜索用户.
     */
    public function searchUserByNickName(string $query, DataIsolation $dataIsolation): array
    {
        return $this->userRepository->searchByNickName($query, $dataIsolation->getCurrentOrganizationCode());
    }

    /**
     * 搜索用户昵称（全magic平台检索）.
     */
    public function searchUserByNickNameInMagic(string $query): array
    {
        return $this->userRepository->searchByNickNameInMagic($query);
    }

    /**
     * 将 flowCodes 设置到 friendQueryDTO 中,并返回 flowCode是否是该用户的好友.
     * @return array<string, MagicFriendEntity>
     */
    public function getUserAgentFriendsList(FriendQueryDTO $friendQueryDTO, DataIsolation $dataIsolation): array
    {
        $userIdToFlowCodeMaps = $this->setUserIdsByAiCodes($friendQueryDTO, $dataIsolation);
        $friendList = $this->friendRepository->getFriendList($friendQueryDTO, $dataIsolation->getCurrentUserId());
        $flowFriends = [];
        // 用 flowCode 置换 friendId
        foreach ($friendList as $friend) {
            $friendId = $friend->getFriendId();
            if (isset($userIdToFlowCodeMaps[$friendId])) {
                $friendFlowCode = $userIdToFlowCodeMaps[$friendId];
                $flowFriends[$friendFlowCode] = $friend;
            }
        }
        return $flowFriends;
    }

    /**
     * @return MagicFriendEntity[]
     */
    public function getUserFriendList(FriendQueryDTO $friendQueryDTO, DataIsolation $dataIsolation): array
    {
        $this->setUserIdsByAiCodes($friendQueryDTO, $dataIsolation);
        return $this->friendRepository->getFriendList($friendQueryDTO, $dataIsolation->getCurrentUserId());
    }

    /**
     * @return MagicUserEntity[]
     */
    public function getUserByIdsWithoutOrganization(array $ids, array $column = ['*']): array
    {
        return $this->userRepository->getUserByIdsAndOrganizations($ids, [], $column);
    }

    public function addUserManual(string $userId, string $userManual): void
    {
        $this->userRepository->addUserManual($userId, $userManual);
    }

    public function updateUserOptionByIds(array $userIds, ?UserOption $userOption = null): int
    {
        if (empty($userIds)) {
            return 0;
        }
        return $this->userRepository->updateUserOptionByIds($userIds, $userOption);
    }

    /**
     * 麦吉用户体系下的登录校验.
     * @return LoginResponseDTO[]
     */
    public function magicUserLoginCheck(string $authorization, MagicEnvironmentEntity $magicEnvironmentEntity, ?string $magicOrganizationCode = null): array
    {
        // 生成缓存键和锁键
        $cacheKey = md5(sprintf('OrganizationUserLogin:auth:%s:env:%s:', $authorization, $magicEnvironmentEntity->getId()));
        $lockKey = $this->generateLockKey(PlatformType::Magic, $authorization);

        // 尝试从缓存获取结果
        $cachedResult = $this->getCachedLoginCheckResult($cacheKey);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        // 加锁处理，防止并发请求
        $owner = $this->acquireLock($lockKey);

        try {
            // 处理麦吉用户系统的token，获取magicId和userId
            $tokenDTO = new MagicTokenEntity();
            $tokenDTO->setType(MagicTokenType::Account);
            $tokenDTO->setToken($authorization);
            $magicUserToken = $this->magicTokenRepository->getTokenEntity($tokenDTO);

            if ($magicUserToken === null) {
                ExceptionBuilder::throw(ChatErrorCode::AUTHORIZATION_INVALID);
            }

            $magicId = $magicUserToken->getTypeRelationValue();

            // 查询用户并处理组织关系，查询麦吉用户
            $magicUserEntities = $this->userRepository->getUserByMagicIds([$magicId]);
            if (empty($magicUserEntities)) {
                ExceptionBuilder::throw(ChatErrorCode::USER_NOT_CREATE_ACCOUNT);
            }

            // 构建返回结果
            $loginResponses = [];
            foreach ($magicUserEntities as $magicUserEntity) {
                $currentOrgCode = $magicUserEntity->getOrganizationCode();
                $loginResponseDTO = new LoginResponseDTO();
                $loginResponseDTO->setMagicId($magicUserEntity->getMagicId())
                    ->setMagicUserId($magicUserEntity->getUserId())
                    ->setMagicOrganizationCode($currentOrgCode)
                    ->setThirdPlatformOrganizationCode($currentOrgCode)
                    ->setThirdPlatformUserId($magicId);

                $loginResponses[] = $loginResponseDTO;
            }
            // 缓存结果
            $this->cacheLoginCheckResult($cacheKey, $loginResponses);

            return $loginResponses;
        } finally {
            // 释放锁
            $this->locker->release($lockKey, $owner);
        }
    }

    /**
     * @return array<UserDetailDTO>
     */
    public function getUserDetailByUserIdsInMagic(array $userIds): array
    {
        // 从 user表拿基本信息
        $users = $this->userRepository->getUserByIdsAndOrganizations($userIds);
        // 解析头像等信息
        $magicIds = array_column($users, 'magic_id');
        // 从 account 表拿手机号真名等信息
        $accounts = $this->accountRepository->getAccountInfoByMagicIds($magicIds);
        return UserAssembler::getUsersDetail($users, $accounts);
    }

    public function searchUserByPhoneOrRealNameInMagic(string $query): array
    {
        $accounts = $this->accountRepository->searchUserByPhoneOrRealName($query);
        if (empty($accounts)) {
            return [];
        }
        $magicIds = array_column($accounts, 'magic_id');
        return $this->userRepository->getUserByAccountsInMagic($magicIds);
    }

    /**
     * 根据用户ID获取用户手机号.
     */
    public function getUserPhoneByUserId(string $userId): ?string
    {
        // 先获取用户信息
        $user = $this->userRepository->getUserById($userId);
        if ($user === null) {
            return null;
        }

        // 通过 magic_id 获取账号信息
        $account = $this->accountRepository->getAccountInfoByMagicId($user->getMagicId());
        if ($account === null) {
            return null;
        }

        return $account->getPhone();
    }

    protected function setUserIdsByAiCodes(FriendQueryDTO $friendQueryDTO, DataIsolation $dataIsolation): array
    {
        $userIdToFlowCodeMaps = [];
        if (! empty($friendQueryDTO->getAiCodes())) {
            // 根据 ai code 查询 magic id
            $accounts = $this->accountRepository->getAccountInfoByAiCodes($friendQueryDTO->getAiCodes());
            $magicIds = array_column($accounts, 'magic_id');
            // 转用户 Id
            $users = $this->userRepository->getUserByAccountsAndOrganization($magicIds, $dataIsolation->getCurrentOrganizationCode());
            $userIds = array_column($users, 'user_id');
            $friendQueryDTO->setUserIds($userIds);
            $accounts = array_column($accounts, null, 'magic_id');
            foreach ($users as $user) {
                /** @var null|AccountEntity $accountEntity */
                $accountEntity = $accounts[$user['magic_id']] ?? null;
                if (isset($accountEntity)) {
                    $userIdToFlowCodeMaps[$user['user_id']] = $accountEntity->getAiCode();
                }
            }
        }
        return $userIdToFlowCodeMaps;
    }

    protected function getAgents(array $popular, array $latest): array
    {
        // 根据magic_id,查账号详情
        $magicIds[] = array_column($popular, 'magic_id');
        $magicIds[] = array_column($latest, 'magic_id');
        $magicIds = array_values(array_unique(array_merge(...$magicIds)));
        $accounts = $this->accountRepository->getAccountInfoByMagicIds($magicIds);
        return [
            'popular' => UserAssembler::getAgentList($popular, $accounts),
            'latest' => UserAssembler::getAgentList($latest, $accounts),
        ];
    }

    /**
     * 生成锁键.
     */
    protected function generateLockKey(PlatformType $platformType, string $authorization): string
    {
        return sprintf('get%sUserInfoFromKeewood:%s', $platformType->name, md5($authorization));
    }

    /**
     * 缓存登录校验结果.
     * @param array<LoginResponseDTO> $result
     */
    protected function cacheLoginCheckResult(string $cacheKey, array $result): void
    {
        // 为了兼容缓存，需要将DTO对象转换为数组存储
        $cacheDTOArray = array_map(static function ($dto) {
            return $dto->toArray();
        }, $result);
        $this->redis->setex($cacheKey, 60, Json::encode($cacheDTOArray));
    }

    /**
     * 获取互斥锁
     * @return string 锁所有者标识
     */
    protected function acquireLock(string $lockKey): string
    {
        try {
            $owner = random_bytes(10);
            $this->locker->mutexLock($lockKey, $owner, 10);
            return $owner;
        } catch (Throwable) {
            ExceptionBuilder::throw(MagicAccountErrorCode::REQUEST_TOO_FREQUENT);
        }
    }

    /**
     * 释放互斥锁
     */
    protected function releaseLock(string $lockKey, string $owner): void
    {
        $this->locker->release($lockKey, $owner);
    }

    /**
     * 获取缓存的登录校验结果.
     * @return null|array<LoginResponseDTO>
     */
    private function getCachedLoginCheckResult(string $cacheKey): ?array
    {
        $loginCache = $this->redis->get($cacheKey);
        if (! empty($loginCache)) {
            $cachedData = Json::decode($loginCache);
            // 将缓存中的数组转换为DTO对象
            return array_map(static function ($item) {
                return new LoginResponseDTO($item);
            }, $cachedData);
        }

        return null;
    }
}
