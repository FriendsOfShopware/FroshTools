<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security;

use Doctrine\DBAL\Connection;

/**
 * Reads all accounts that can authenticate against the Admin API: admin users,
 * integrations and their ACL roles.
 *
 * Exposes ids and security relevant attributes only - no usernames, names,
 * e-mail addresses or access keys. Aggregated across many shops those would
 * form a credential stuffing list, so do not add them.
 */
class AdminAccountService
{
    private const ADMIN_USER_PATH = '/admin#/sw/users/permissions/user/detail/';
    private const ADMIN_INTEGRATION_PATH = '/admin#/sw/integration/index';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUsers(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`u`.`id`)) AS `id`,
                    `u`.`active`,
                    `u`.`admin`,
                    `u`.`created_at`,
                    `u`.`updated_at`,
                    `u`.`last_updated_password_at`,
                    COUNT(DISTINCT `k`.`id`) AS `access_key_count`,
                    MAX(`k`.`last_usage_at`) AS `access_key_last_usage_at`,
                    `r`.`user_id` IS NOT NULL AS `password_recovery_pending`
             FROM `user` AS `u`
             LEFT JOIN `user_access_key` AS `k` ON `k`.`user_id` = `u`.`id`
             LEFT JOIN `user_recovery` AS `r` ON `r`.`user_id` = `u`.`id`
             GROUP BY `u`.`id`, `r`.`user_id`
             ORDER BY `u`.`created_at`',
        );

        $roles = $this->fetchRoleAssignments(
            'SELECT LOWER(HEX(`user_id`)) AS `owner_id`, LOWER(HEX(`acl_role_id`)) AS `role_id` FROM `acl_user_role`',
        );

        $users = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];

            $users[] = [
                'id' => $id,
                'active' => (bool) $row['active'],
                'admin' => (bool) $row['admin'],
                'aclRoleIds' => $roles[$id] ?? [],
                'accessKeyCount' => (int) $row['access_key_count'],
                'accessKeyLastUsageAt' => $this->formatDate($row['access_key_last_usage_at']),
                'passwordRecoveryPending' => (bool) $row['password_recovery_pending'],
                'lastUpdatedPasswordAt' => $this->formatDate($row['last_updated_password_at']),
                'createdAt' => $this->formatDate($row['created_at']),
                'updatedAt' => $this->formatDate($row['updated_at']),
                'adminPath' => self::ADMIN_USER_PATH . $id,
            ];
        }

        return $users;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getIntegrations(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`i`.`id`)) AS `id`,
                    `i`.`label`,
                    `i`.`admin`,
                    `i`.`last_usage_at`,
                    `i`.`deleted_at`,
                    `i`.`created_at`,
                    `i`.`updated_at`,
                    `a`.`name` AS `app_name`,
                    LOWER(HEX(`a`.`acl_role_id`)) AS `app_acl_role_id`
             FROM `integration` AS `i`
             LEFT JOIN `app` AS `a` ON `a`.`integration_id` = `i`.`id`
             ORDER BY `i`.`created_at`',
        );

        $roles = $this->fetchRoleAssignments(
            'SELECT LOWER(HEX(`integration_id`)) AS `owner_id`, LOWER(HEX(`acl_role_id`)) AS `role_id` FROM `integration_role`',
        );

        $integrations = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $appName = $row['app_name'] !== null ? (string) $row['app_name'] : null;

            // An app grants its integration permissions through `app.acl_role_id`,
            // not through `integration_role`, and that path even takes precedence.
            $roleIds = $roles[$id] ?? [];
            if ($row['app_acl_role_id'] !== null) {
                $roleIds[] = (string) $row['app_acl_role_id'];
                $roleIds = array_values(array_unique($roleIds));
                sort($roleIds);
            }

            $integrations[] = [
                'id' => $id,
                'label' => (string) $row['label'],
                'admin' => (bool) $row['admin'],
                'aclRoleIds' => $roleIds,
                // App-owned integrations come and go with the app, no manual credentials.
                'managedByApp' => $appName !== null,
                'appName' => $appName,
                'lastUsageAt' => $this->formatDate($row['last_usage_at']),
                'deletedAt' => $this->formatDate($row['deleted_at']),
                'createdAt' => $this->formatDate($row['created_at']),
                'updatedAt' => $this->formatDate($row['updated_at']),
                'adminPath' => self::ADMIN_INTEGRATION_PATH,
            ];
        }

        return $integrations;
    }

    /**
     * Hashed so a role gaining privileges stays detectable without shipping the
     * full list on every poll.
     *
     * @return list<array<string, mixed>>
     */
    public function getAclRoles(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, `name`, `privileges`, `deleted_at`, `created_at`, `updated_at`
             FROM `acl_role`
             ORDER BY `created_at`',
        );

        $roles = [];
        foreach ($rows as $row) {
            $privileges = $this->decodePrivileges($row['privileges']);
            sort($privileges);

            $roles[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'privilegeCount' => \count($privileges),
                'privilegesHash' => hash('sha256', implode("\n", $privileges)),
                'deletedAt' => $this->formatDate($row['deleted_at']),
                'createdAt' => $this->formatDate($row['created_at']),
                'updatedAt' => $this->formatDate($row['updated_at']),
            ];
        }

        return $roles;
    }

    /**
     * @return array<string, list<string>>
     */
    private function fetchRoleAssignments(string $sql): array
    {
        $assignments = [];
        foreach ($this->connection->fetchAllAssociative($sql) as $row) {
            $assignments[(string) $row['owner_id']][] = (string) $row['role_id'];
        }

        foreach ($assignments as &$roleIds) {
            sort($roleIds);
        }

        return $assignments;
    }

    /**
     * @return list<string>
     */
    private function decodePrivileges(mixed $value): array
    {
        if (!\is_string($value) || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($decoded)) {
            return [];
        }

        return array_values(array_map(strval(...), $decoded));
    }

    private function formatDate(mixed $value): ?string
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(\DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }
}
