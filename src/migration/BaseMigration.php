<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Kernel\migration;

use Yii;
use yii\base\NotSupportedException;
use yii\db\Exception;
use yii\db\Migration;
use yii\db\TableSchema;
use yii\helpers\ArrayHelper;

class BaseMigration extends Migration
{
    protected string|null $tableOptions = null;

    public function init(): void
    {
        parent::init();
        $this->tableOptions = $this->getTableOptionsForDriver($this->db->driverName);
    }

    /**
     * Возвращает опции таблицы в зависимости от драйвера БД
     */
    private function getTableOptionsForDriver(string $driverName): ?string
    {
        return match ($driverName) {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            'mysql' => 'CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE=InnoDB',
            'pgsql' => null,
            'sqlite' => null,
            default => null,
        };
    }

    /**
     * @throws NotSupportedException
     */
    public function createIndexes(string $tableName, string|array $columns, bool $isPK = false, bool $unique = false): void
    {
        $indexName = $this->getIndexName($tableName, $columns, $isPK);
        if (!$this->existIndex($tableName, $indexName)) {
            if ($isPK) {
                $this->addPrimaryKey($indexName, $tableName, $columns);
            } else {
                $this->createIndex($indexName, $tableName, $columns, $unique);
            }
        }
    }

    // Более узкие ограничения типов по сравнению с \yii\db\Migration::addForeignKey. Можно расширить при желании.
    public function createFKs(string $tableName, string|array $columns, string $refTable, string $refColumn, string|null $delete = null, string|null $update = null): void
    {
        $FKName = $this->getFKName($tableName, $columns);
        if (!$this->existFKs($tableName, $FKName)) {
            $this->addForeignKey(
                $FKName,
                $tableName,
                $columns,
                $refTable,
                $refColumn,
                $delete,
                $update
            );
        }
    }

    /**
     * @throws NotSupportedException
     */
    public function removeIndexes(string $tableName, array $columns, bool $isPK = false): void
    {
        $indexName = $this->getIndexName($tableName, $columns, $isPK);

        if ($this->existIndex($tableName, $indexName)) {
            $this->dropIndex($indexName, $tableName);
        }
    }

    public function removeFKs(string $tableName, string|array $columns): void
    {
        $FKName = $this->getFKName($tableName, $columns);

        if ($this->existFKs($tableName, $FKName)) {
            $this->dropForeignKey(
                $FKName,
                $tableName
            );
        }
    }

    /**
     * Генерирует удобное для меня название индекса.
     * TODO Не учитывает префикс, добавить обработку префикса. // '{{%idx-auth_assignment-user_id}}'
     *
     * @param string $tableName - Название таблицы, для которой генерируется индекс
     * @param string|array $columns - Массив названий столбцов, для которых генерируется индекс.
     * @param bool $isPK - Если это Primary key.
     * @return string
     */
    public function getIndexName(string $tableName, string|array $columns, bool $isPK): string
    {
        $columnsString = is_array($columns) ? implode('_', $columns) : $columns;

        if ($isPK) {
            return $columnsString . '-PK';
        }

        return 'idx-' . str_replace(['{', '}', '%'], ['', '', ''], $tableName) . '-' . $columnsString;
    }

    public function getFKName(string $tableName, string|array $columns): string
    {
        $columnsString = is_array($columns) ? implode('_', $columns) : $columns;
        return 'fk-' . str_replace(['{', '}', '%'], ['', '', ''], $tableName) . '-' . $columnsString;
    }

    /**
     * @throws NotSupportedException
     */
    public function existIndex(string $tableName, string $indexName): bool
    {
        $table = $this->getTableSchema($tableName);
        $uniqueIndexes = $this->getDb()->schema->findUniqueIndexes($table);
        $hasIndex = ArrayHelper::keyExists($indexName, $uniqueIndexes);

        if ($hasIndex) {
            return true;
        }
        return false;
    }

    public function existFKs(string $tableName, string $FKName): bool
    {
        $tableSchema = $this->getTableSchema($tableName);
        return array_key_exists($FKName, $tableSchema->foreignKeys);
    }

    public function existTable(string $tableName): bool
    {
        return $this->getTableSchema($tableName) !== null;
    }

    public function getTableSchema(string $tableName): ?TableSchema
    {
        $this->getDb()->schema->refreshTableSchema($tableName);
        return $this->getDb()->getTableSchema($tableName, true);
    }

    /**
     * @throws NotSupportedException
     */
    public function safeDown(): void
    {
        parent::safeDown();

        // Отключение проверки внешних ключей (в зависимости от драйвера БД)
        $this->disableForeignKeyChecks();

        // Удаление внешних ключей
        $tableSchema = $this->getTableSchema(static::TABLE_NAME);
        if ($tableSchema !== null) {
            foreach ($tableSchema->foreignKeys as $fkName => $fk) {
                $this->dropForeignKey($fkName, static::TABLE_NAME);
            }
            // Удаление индексов
            $indexes = $this->db->schema->findUniqueIndexes($tableSchema);
            foreach ($indexes as $indexName => $columns) {
                $this->dropIndex($indexName, static::TABLE_NAME);
            }
            // Удаление таблицы
            if ($this->existTable(static::TABLE_NAME)) {
                $this->dropTable(static::TABLE_NAME);
            }
        }

        // Включение проверки внешних ключей
        $this->enableForeignKeyChecks();

        parent::safeDown();
    }

    /**
     * Отключает проверку внешних ключей (в зависимости от драйвера БД)
     */
    private function disableForeignKeyChecks(): void
    {
        try {
            match ($this->db->driverName) {
                'mysql' => $this->db->createCommand('SET foreign_key_checks = 0')->execute(),
                'pgsql' => $this->db->createCommand('SET CONSTRAINTS ALL DEFERRED')->execute(),
                'sqlite' => $this->db->createCommand('PRAGMA foreign_keys = OFF')->execute(),
                default => null,
            };
        } catch (\Exception $e) {
            Yii::warning("Failed to disable foreign key checks: " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Включает проверку внешних ключей (в зависимости от драйвера БД)
     */
    private function enableForeignKeyChecks(): void
    {
        try {
            match ($this->db->driverName) {
                'mysql' => $this->db->createCommand('SET foreign_key_checks = 1')->execute(),
                'pgsql' => $this->db->createCommand('SET CONSTRAINTS ALL IMMEDIATE')->execute(),
                'sqlite' => $this->db->createCommand('PRAGMA foreign_keys = ON')->execute(),
                default => null,
            };
        } catch (\Exception $e) {
            Yii::warning("Failed to enable foreign key checks: " . $e->getMessage(), __METHOD__);
        }
    }

}
