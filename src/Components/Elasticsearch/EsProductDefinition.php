<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Elasticsearch;

use Doctrine\DBAL\Connection;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

class EsProductDefinition extends AbstractElasticsearchDefinition
{
    protected AbstractElasticsearchDefinition $inner;
    protected Connection $connection;
    protected array $fields;
    protected int $minimumShouldMatch;

    public function __construct(
        AbstractElasticsearchDefinition $elasticsearchDefinition,
        Connection $connection,
        array $fields,
        int $minimumShouldMatch
    ) {
        $this->inner = $elasticsearchDefinition;
        $this->connection = $connection;
        $this->fields = $fields;
        $this->minimumShouldMatch = $minimumShouldMatch;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->inner->getEntityDefinition();
    }

    public function extendEntities(EntityCollection $entityCollection): EntityCollection
    {
        return $entityCollection;
    }

    public function fetch(array $ids, Context $context): array
    {
        $data = $this->inner->fetch($ids, $context);

        $fields = $this->getFields();

        if ($fields === []) {
            return $data;
        }

        $query = new QueryBuilder($this->connection);
        $query
            ->addSelect('LOWER(HEX(p.id)) AS id')
            ->from('product', 'p')
            ->leftJoin('p', 'product', 'pp', 'p.parent_id = pp.id AND pp.version_id = :liveVersionId')
            ->leftJoin('p', '(:productTranslationQuery:)', 'product_translation_main', 'product_translation_main.product_id = p.id')
            ->leftJoin('p', '(:productTranslationQuery:)', 'product_translation_parent', 'product_translation_parent.product_id = p.parent_id')
            ->where('p.id IN (:ids)')
            ->andWhere('p.version_id = :liveVersionId')
            ->andWhere('(p.child_count = 0 OR p.parent_id IS NOT NULL)')
            ->groupBy('p.id')
        ;

        foreach ($fields as [$field, $config]) {
            if ($config['translateable']) {
                $transSelect = $this->buildCoalesce(
                    [
                        'product_translation.translation.' . $field->getStorageName(),
                        'product_translation.translation.fallback_1.' . $field->getStorageName(),
                        'product_translation.translation.fallback_2.' . $field->getStorageName(),
                    ],
                    $context
                );

                $query->addSelect($transSelect . ' as ' . $field->getPropertyName());
            } else {
                $query->addSelect(\sprintf('IFNULL(p.%s, pp.%s) AS %s', $field->getStorageName(), $field->getStorageName(), $field->getPropertyName()));
            }
        }

        $translationQuery = $this->getTranslationQuery($fields, $context);

        $replacements = [
            ':productTranslationQuery:' => $translationQuery->getSQL(),
        ];

        $additionalData = $this->connection->fetchAll(
            \str_replace(\array_keys($replacements), $replacements, $query->getSQL()),
            \array_merge([
                'ids' => $ids,
                'liveVersionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ], $translationQuery->getParameters()),
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ]
        );

        foreach (FetchModeHelper::groupUnique($additionalData) as $key => $value) {
            $applied = false;

            foreach ($fields as [$field, $options]) {
                $value = $this->convertValue($field, $value[$field->getPropertyName()]);
                $data[$key][$field->getPropertyName()] = $value;

                if ($options['include_in_fulltext']) {
                    if (\is_array($value)) {
                        $value = \implode(' ', $value);
                    }

                    $data[$key]['fullText'] .= $value;
                    $applied = true;
                }

                if ($options['include_in_fulltext_boosted']) {
                    $data[$key]['fullTextBoosted'] .= $value;
                    $applied = true;
                }
            }

            if ($applied) {
                $data[$key]['fullText'] = $this->stripText($data[$key]['fullText']);
                $data[$key]['fullTextBoosted'] = $this->stripText($data[$key]['fullTextBoosted']);
            }
        }

        return $data;
    }

    private function convertValue(Field $field, $value)
    {
        switch (true) {
            case $field instanceof ListField:
                if ($value === null) {
                    return [];
                }

                return \array_values(\json_decode($value, true, 512, \JSON_THROW_ON_ERROR));
            case $field instanceof JsonField:
                if ($value === null) {
                    return null;
                }

                return \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
            case $field instanceof BoolField:
                return (bool) $value;
            case $field instanceof IntField:
                return (int) $value;
            case $field instanceof FloatField:
                return (float) $value;
            case $field instanceof IdField:
            case $field instanceof FkField:
                if ($value === null) {
                    return null;
                }

                return Uuid::fromBytesToHex($value);
            default:
                return $value;
        }
    }

    public function getMapping(Context $context): array
    {
        $mapping = $this->inner->getMapping($context);

        /**
         * @var Field $field */
        foreach ($this->getFields() as [$field, $options]) {
            $propertyName = $field->getPropertyName();
            $mapping['properties'][$propertyName] = $options['mapping'];
        }

        return $mapping;
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        $query = $this->inner->buildTermQuery($context, $criteria);

        $term = (string) $criteria->getTerm();

        $query->addParameter('minimum_should_match', $this->minimumShouldMatch);

        foreach ($this->fields as $field) {
            if ($field['query'] !== []) {
                foreach ($field['query'] as $queryItem) {
                    $query->add(
                        $this->buildQuery($field['name'], $queryItem, $term),
                        $queryItem['bool_type']
                    );
                }
            }
        }

        return $query;
    }

    private function buildCoalesce(array $fields, Context $context): string
    {
        $fields = \array_splice($fields, 0, \count($context->getLanguageIdChain()));

        $coalesce = 'COALESCE(';

        foreach ($fields as $field) {
            foreach (['product_translation_main', 'product_translation_parent'] as $join) {
                $coalesce .= \sprintf('%s.`%s`', $join, $field) . ',';
            }
        }

        return mb_substr($coalesce, 0, -1) . ')';
    }

    private function getTranslationQuery(array $fields, Context $context): QueryBuilder
    {
        $query = new QueryBuilder($this->connection);

        $productAlias = 'p';
        $query->from('product ', $productAlias);
        $parentIdSelector = 'SELECT DISTINCT `p`.parent_id FROM `product` p WHERE p.id IN(:ids)';
        $query->select(EntityDefinitionQueryHelper::escape($productAlias) . '.id AS product_id');
        $query->where(EntityDefinitionQueryHelper::escape($productAlias) . '.id IN (:ids) OR '
            . EntityDefinitionQueryHelper::escape($productAlias) . '.id IN(' . $parentIdSelector . ')');

        $chain = $context->getLanguageIdChain();

        $firstAlias = 'product_translation.translation';

        foreach ($chain as $i => $language) {
            $languageQuery = new QueryBuilder($this->connection);

            $alias = 'pt';
            $outerAlias = 'product_translation.translation.fallback_' . $i;
            $languageParam = 'languageId' . $i;
            if ($i === 0) {
                $outerAlias = $firstAlias;
                $languageParam = 'languageId';
            }

            $languageQuery->from('product_translation ' . EntityDefinitionQueryHelper::escape($alias));
            $languageQuery->andWhere(EntityDefinitionQueryHelper::escape($alias) . '.language_id = :' . $languageParam);
            $languageQuery->addSelect(EntityDefinitionQueryHelper::escape($alias) . '.product_id');

            foreach ($fields as [$field, $options]) {
                if ($options['translateable']) {
                    $storageName = $field->getStorageName();
                    $languageQuery->addSelect(EntityDefinitionQueryHelper::escape($alias) . '.' . EntityDefinitionQueryHelper::escape($storageName));

                    $query->addSelect(
                        EntityDefinitionQueryHelper::escape($outerAlias) . '.' . EntityDefinitionQueryHelper::escape($storageName) . ' AS ' . EntityDefinitionQueryHelper::escape($outerAlias . '.' . $storageName),
                    );
                }
            }

            $query->leftJoin(
                $productAlias,
                '(' . $languageQuery->getSQL() . ')',
                EntityDefinitionQueryHelper::escape($outerAlias),
                EntityDefinitionQueryHelper::escape($productAlias) . '.id = '
                . EntityDefinitionQueryHelper::escape($outerAlias) . '.product_id'
            );
            $query->setParameter($languageParam, Uuid::fromHexToBytes($language));
        }

        return $query;
    }

    private function getField(array $field): array
    {
        $definition = $this->inner->getEntityDefinition();
        $field['translateable'] = false;

        $obj = $definition->getField($field['name']);

        if ($obj instanceof AssociationField) {
            throw new \RuntimeException(\sprintf('Association field %s is not supported to index', $obj->getPropertyName()));
        }

        if ($obj instanceof TranslatedField) {
            $field['translateable'] = true;
            $obj = $definition->getTranslationDefinition()->getField($field['name']);

            return [$obj, $field];
        }

        return [$obj, $field];
    }

    private function getFields(): array
    {
        return \array_map([$this, 'getField'], $this->fields);
    }

    private function buildQuery(string $field, array $queryItem, string $term): BuilderInterface
    {
        $field = $queryItem['field'] ?? $field;

        switch ($queryItem['type']) {
            case 'match':
                return new MatchQuery($field, $term, $queryItem['options']);
            case 'match_phrase_prefix':
                return new MatchPhrasePrefixQuery($field, $term, $queryItem['options']);
            case 'wildcard':
                return new WildcardQuery($field, '*' . \mb_strtolower($term) . '*', $queryItem['options']);
            default:
                throw new \RuntimeException(\sprintf('Type %s is not supported', $queryItem['type']));
        }
    }
}
