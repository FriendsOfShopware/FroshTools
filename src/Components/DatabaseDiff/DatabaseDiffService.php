<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff;

use Frosh\Tools\Components\DatabaseDiff\Swdb\Schema;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DatabaseDiffService
{
    public function __construct(
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
        private readonly HttpClientInterface $httpClient,
        private readonly DataValidator $validator,
    ) {
    }

    /**
     * @throws \RuntimeException
     */
    public function getAvailableVersions(): array
    {
        $url = 'https://swdb.dev/api/schemas.json';

        try {
            $json = $this->httpClient->request('GET', $url)
                ->getContent(false);
            $json = \trim((string) $json);
            $data = Json::decodeToList($json);

            [$data, $errors] = $this->createVersions($data);
            return $data;
        } catch (\Throwable $error) {
            throw new \RuntimeException('Failed to load available versions', previous: $error);
        }
    }

    /**
     * Separates valid from invalid entries in the version list response.
     *
     * @return array{0: array, 1: array}
     */
    private function createVersions(array $data): array
    {
        // TODO: Implement full validation definition.
        $definition = (new DataValidationDefinition('frosh_tools.shopware_database_schema.version'))
            ->add('version', new Assert\Type('string'))
            ->add('slug', new Assert\Type('string'))
            ->add('major', new Assert\Type('int'), new Assert\GreaterThanOrEqual(6))
            ->add('minor', new Assert\Type('int'))
            ->add('majorMinor', new Assert\Type('string'))
            ->add('tableCount', new Assert\Type('integer'))
            ->add('url', new Assert\Type('string'), new Assert\Url())
            ->add('schemaUrl', new Assert\Type('string'), new Assert\Url());

        $versions = [];
        $errors   = [];

        foreach ($data as $versionInfo) {
            try {
                $this->validator->validate($versionInfo, $definition);

                $versions[$versionInfo['version']] = $versionInfo;
            } catch (ConstraintViolationException $exception) {
                $errors[$versionInfo['version']] = [
                    'error'      => $exception->getMessage(),
                    'violations' => (string) $exception->getViolations(),
                    'data'       => $versionInfo,
                ];
            }
        }

        return [$versions, $errors];
    }

    /**
     * @throws \RuntimeException
     */
    public function getDatabaseSchema(string $version): Schema
    {
        // TODO: Add resolution to the next higher/lower version available. Also, maybe handle the DEV version alias.
        $version = $this->parseVersionSlug($version);
        $url     = 'https://swdb.dev/api/schemas/%s.schema.json';

        try {
            $json = $this->httpClient->request('GET', \sprintf($url, $version))
                ->getContent(false);
            $json = \trim((string) $json);
            $data = Json::decodeToList($json);

            [$tables, $errors] = $this->createSchema($data);

            // Create schema from valid data and attach errors as struct extension.
            $schema = Schema::createFromData($tables, $version);
            $schema->addArrayExtension('errors', $errors);
            return $schema;
        } catch (\Throwable $error) {
            throw new \RuntimeException(\sprintf('Failed to load or create schema for version %s', $version),
                previous: $error);
        }
    }

    public function parseVersionSlug(string $version): string
    {
        return \str_replace('RC', 'rc',
            \ltrim(Feature::normalizeName($version), 'v')
        );
    }

    /**
     * Separates valid from invalid entries in the schema response.
     *
     * @return array{0: array, 1: array}
     */
    private function createSchema(array $data): array
    {
        // TODO: Implement full validation definition.
        $definition = $this->getValidationDefinition();

        $tables = [];
        $errors = [];

        foreach ($data as $tableInfo) {
            try {
                $this->validator->validate($tableInfo, $definition);

                $tables[$tableInfo['table']] = $tableInfo;
            } catch (ConstraintViolationException $exception) {
                $errors[$tableInfo['table']] = [
                    'error'      => $exception->getMessage(),
                    'violations' => (string) $exception->getViolations(),
                    'data'       => $tableInfo,
                ];
            }
        }

        return [$tables, $errors];
    }

    public function createSchemaDiff(Schema $versionA, Schema $versionB): array
    {
        if ($versionA->version === $versionB->version) {
            return [];
        }

        return \iterator_to_array($this->generateSchemaDiff($versionA, $versionB), false);
    }

    private function generateSchemaDiff(Schema $versionA, Schema $versionB): \Generator
    {
        foreach ($versionA->tables as $tableA) {
            foreach ($versionB->tables as $tableB) {
                if ($tableA->table !== $tableB->table) {
                    continue 1;
                }

                yield from $this->generateTableDiff($tableA, $tableB);

                continue 2;
            }

            // Table removed.
            yield [
                'table'  => $tableA->table,
                'type'   => 'tables',
                'label'  => 'removed',
                'name'   => $tableA->table,
                'valueA' => $tableA->table,
                'valueB' => null,
            ];

            yield from $this->generateTableDiff($tableA, null);
        }

        foreach ($versionB->tables as $tableB) {
            foreach ($versionA->tables as $tableA) {
                if ($tableB->table !== $tableA->table) {
                    continue 1;
                }

                continue 2;
            }

            // Table added.
            yield [
                'table'  => $tableB->table,
                'type'   => 'tables',
                'label'  => 'added',
                'name'   => $tableB->table,
                'valueA' => $tableB->table,
                'valueB' => null,
            ];

            yield from $this->generateTableDiff(null, $tableB);
        }
    }

    private function generateTableDiff(?Swdb\Table $tableA, ?Swdb\Table $tableB): \Generator
    {
        if (null === $tableA && null === $tableB) {
            throw new \InvalidArgumentException('At least one table is required to generate a diff');
        }

        $tableA ??= new Swdb\Table($tableB?->table ?? '');
        $tableB ??= new Swdb\Table($tableA?->table ?? '');

        $properties = ['fields', 'relationsToTable', 'relationsFromTable', 'indexes'];

        // Found the matching table, check members...
        foreach ($properties as $property) {
            // Among each list of members, check for differences.
            foreach ($tableA->{$property} as $memberA) {
                foreach ($tableB->{$property} as $memberB) {
                    if ($memberA->key() !== $memberB->key()) {
                        continue 1;
                    }

                    // Member modified.
                    if ($memberA->compare($memberB)) {
                        yield [
                            'table'  => $memberA->table,
                            'type'   => $memberA->type(),
                            'label'  => 'modified',
                            'name'   => $memberA->key(),
                            'valueA' => $memberA->label(),
                            'valueB' => $memberB->label(),
                        ];
                    }

                    // Found; no need to continue the search.
                    continue 2;
                }

                // Not found, column removed.
                yield [
                    'table'  => $memberA->table,
                    'type'   => $memberA->type(),
                    'label'  => 'removed',
                    'name'   => $memberA->key(),
                    'valueA' => $memberA->label(),
                    'valueB' => null,
                ];
            }

            // Search for added fields.
            foreach ($tableB->{$property} as $memberB) {
                foreach ($tableA->{$property} as $memberA) {
                    if ($memberB->key() !== $memberA->key()) {
                        continue 1;
                    }

                    // Found.
                    continue 2;
                }

                // Not found, column added.
                yield [
                    'table'  => $memberB->table,
                    'type'   => $memberB->type(),
                    'label'  => 'added',
                    'name'   => $memberB->key(),
                    'valueA' => null,
                    'valueB' => $memberB->label(),
                ];
            }
        }
    }

    /**
     * TODO: Add constraints.
     */
    private function getValidationDefinition(): DataValidationDefinition
    {
        return (new DataValidationDefinition('frosh_tools.shopware_database_schema.table'))
            ->addList('fields',
                (new DataValidationDefinition('frosh_tools.shopware_database_schema.table.fields'))
                    ->add('Default')
                    ->add('Extra')
                    ->add('Field')
                    ->add('Key')
                    ->add('Null')
                    ->add('Type')
            )
            ->addList('indexes',
                (new DataValidationDefinition('frosh_tools.shopware_database_schema.table.indexes'))
                    ->add('Columns')
                    ->add('Index')
                    ->add('Index')
            )
            ->addList('relationsFromTable',
                (new DataValidationDefinition('frosh_tools.shopware_database_schema.table.relations_from'))
                    ->add('foreignField')
                    ->add('foreignSchema')
                    ->add('foreignTable')
                    ->add('localField')
            )
            ->addList('relationsToTable',
                (new DataValidationDefinition('frosh_tools.shopware_database_schema.table.relations_to'))
                    ->add('foreignField')
                    ->add('foreignSchema')
                    ->add('foreignTable')
                    ->add('localField')
            )
            ->add('table', new Assert\NotBlank(), new Assert\Type('string'))
        ;
    }
}
