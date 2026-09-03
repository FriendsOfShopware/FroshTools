<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\DatabaseDiff\Swdb;

use Frosh\Tools\Components\DatabaseDiff\Swdb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Json;

#[CoversClass(Swdb\Schema::class)]
#[CoversClass(Swdb\Table::class)]
#[CoversClass(Swdb\TableField::class)]
#[CoversClass(Swdb\TableIndex::class)]
#[CoversClass(Swdb\TableRelation::class)]
#[CoversClass(Swdb\InverseTableRelation::class)]
class ModelTest extends TestCase
{
    public const VERSION_A = '6.7.13.0';
    public const VERSION_B = '6.6.10.0';

    public const JSON__SCHEMA_A           = <<<'JSON'
[{
  "fields": [
    {
      "Field": "token",
      "Type": "varchar(50)",
      "Null": false,
      "Key": "PRI",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "rule_ids",
      "Type": "json",
      "Null": false,
      "Key": "",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "created_at",
      "Type": "datetime(3)",
      "Null": false,
      "Key": "MUL",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "auto_increment",
      "Type": "bigint",
      "Null": false,
      "Key": "UNI",
      "Default": null,
      "Extra": "auto_increment"
    },
    {
      "Field": "compressed",
      "Type": "tinyint(1)",
      "Null": false,
      "Key": "",
      "Default": "0",
      "Extra": ""
    },
    {
      "Field": "payload",
      "Type": "longblob",
      "Null": true,
      "Key": "",
      "Default": null,
      "Extra": ""
    }
  ],
  "relationsFromTable": [],
  "relationsToTable": [],
  "indexes": [
    {
      "Index": "auto_increment",
      "Columns": "auto_increment",
      "Unique": 1
    },
    {
      "Index": "idx.cart.created_at",
      "Columns": "created_at",
      "Unique": 0
    },
    {
      "Index": "PRIMARY",
      "Columns": "token",
      "Unique": 1
    }
  ],
  "table": "cart"
},
{
  "fields": [
    {
      "Field": "token",
      "Type": "char(32)",
      "Null": false,
      "Key": "PRI",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "expires",
      "Type": "datetime(3)",
      "Null": false,
      "Key": "",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "consumed",
      "Type": "tinyint(1)",
      "Null": true,
      "Key": "",
      "Default": "0",
      "Extra": ""
    }
  ],
  "relationsFromTable": [],
  "relationsToTable": [],
  "indexes": [
    {
      "Index": "PRIMARY",
      "Columns": "token",
      "Unique": 1
    }
  ],
  "table": "payment_token"
}]
JSON;
    public const JSON__SCHEMA_B           = <<<'JSON'
[{
  "fields": [
    {
      "Field": "token",
      "Type": "varchar(50)",
      "Null": false,
      "Key": "PRI",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "rule_ids",
      "Type": "json",
      "Null": false,
      "Key": "",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "created_at",
      "Type": "datetime(3)",
      "Null": false,
      "Key": "MUL",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "auto_increment",
      "Type": "bigint",
      "Null": false,
      "Key": "UNI",
      "Default": null,
      "Extra": "auto_increment"
    },
    {
      "Field": "compressed",
      "Type": "tinyint(1)",
      "Null": false,
      "Key": "",
      "Default": "0",
      "Extra": ""
    },
    {
      "Field": "payload",
      "Type": "longblob",
      "Null": true,
      "Key": "",
      "Default": null,
      "Extra": ""
    }
  ],
  "relationsFromTable": [],
  "relationsToTable": [],
  "indexes": [
      {
          "Index": "auto_increment",
          "Columns": "auto_increment",
          "Unique": 1
      },
      {
          "Index": "idx.cart.created_at",
          "Columns": "created_at",
          "Unique": 0
      },
      {
          "Index": "PRIMARY",
          "Columns": "token",
          "Unique": 1
      }
  ],
  "table": "cart"
}, {
  "fields": [
    {
      "Field": "token",
      "Type": "char(32)",
      "Null": false,
      "Key": "PRI",
      "Default": null,
      "Extra": ""
    },
    {
      "Field": "expires",
      "Type": "datetime(3)",
      "Null": false,
      "Key": "",
      "Default": null,
      "Extra": ""
    }
  ],
  "relationsFromTable": [],
  "relationsToTable": [],
  "indexes": [
    {
      "Index": "PRIMARY",
      "Columns": "token",
      "Unique": 1
    }
  ],
  "table": "payment_token"
}]
JSON;

    public static function provideSchemaRaw(): iterable
    {
        yield 'version ' . static::VERSION_A => [self::JSON__SCHEMA_A, static::VERSION_A];
        yield 'version ' . static::VERSION_B => [self::JSON__SCHEMA_B, static::VERSION_B];
    }

    #[DataProvider('provideSchemaRaw')]
    public function testCreateModelFromData(string $schema, string $version): void
    {
        $data   = Json::decodeToList($schema);
        $schema = Swdb\Schema::createFromData($data, $version);

        self::assertSame($version, $schema->version);
        self::assertSameSize($data, $schema->tables);
    }
}
