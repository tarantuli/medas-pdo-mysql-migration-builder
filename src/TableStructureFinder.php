<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\EntityManager\Attributes\Relations\Action;
use Medas\MigrationBuilder\Structure\{Blueprint, Blueprint\ForeignKey, Blueprint\Index};
use Medas\PdoStorage\Table;

#[Service]
readonly class TableStructureFinder
{
    public function __construct(
        private DefinitionToFieldConverter                      $definitionToFieldConverter,
        private TableStructureFinder\TableStructureStringFinder $tableStructureStringFinder,
    )
    {
    }

    public function find(Table $table): Blueprint|null
    {
        $job = new TableStructureFinder\Job($table->storage(), $table);

        $job->createTable = $this->tableStructureStringFinder->find($table);

        if ($job->createTable === null) {
            return null;
        }

        $this->findName($job);
        $this->findFields($job);
        $this->findPrimaryKey($job);
        $this->findKeys($job);
        $this->findForeignKeys($job);

        return $job->blueprint;
    }

    protected function findName(TableStructureFinder\Job $job): void
    {
        if (!preg_match('/create table `([^`]+)/i', $job->createTable, $match)) {
            return;
        }

        $job->blueprint->name = $match[1];
    }

    protected function findFields(TableStructureFinder\Job $job): void
    {
        if (!preg_match_all('/^ +`([^`]+)` (.+?),?$/m', $job->createTable, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $job->blueprint->addField($this->definitionToFieldConverter->convert($match[1], $match[2]));
        }
    }

    protected function findPrimaryKey(TableStructureFinder\Job $job): void
    {
        if (!preg_match('/primary key \(([^)]+)\)/i', $job->createTable, $match)) {
            return;
        }

        $index = new Index(isPrimary: true);

        foreach ($this->getNames($match[1]) as $name) {
            $index->addField($job->blueprint->fieldByName($name));
        }

        $index->isUnique = true;

        $job->blueprint->addIndex($index);
    }

    protected function findKeys(TableStructureFinder\Job $job): void
    {
        if (!preg_match_all(
            '/(?<isUnique>unique )?key `(?<name>[^`]+)` \((?<fields>(?:[^()]|\(\d+\))+)\)/i',
            $job->createTable,
            $matches,
            PREG_SET_ORDER
        )) {
            return;
        }

        foreach ($matches as $match) {
            $index = new Index();
            $names = $this->getNames($match['fields']);

            foreach ($names as $name) {
                $index->addField($job->blueprint->fieldByName($name));
            }

            $index->isUnique = $match['isUnique'] !== '';

            if (count($index->fields()) === 1) {
                if ($index->isUnique) {
                    $job->blueprint->fieldByName($index->fields()[0]->name)->isUnique = true;
                }
                else {
                    $job->blueprint->fieldByName($index->fields()[0]->name)->isIndex = true;
                }
            }

            $job->blueprint->addIndex($index);
        }
    }

    protected function getNames(string $nameString): array
    {
        $names = explode(',', $nameString);

        return array_map(function (string $name): string {
            // Strip backticks and any trailing prefix length e.g. (500)
            $name = preg_replace('/ \(\d+\)$/', '', $name);

            return trim($name, '`');
        }, $names);
    }

    protected function findForeignKeys(TableStructureFinder\Job $job): void
    {
        if (!preg_match_all(
            '/constraint `(?<name>[^`]+)` foreign key \(`(?<field>[^`]+)`\) references `(?<table>[^`]+)` \(`(?<reference>[^`]+)`\)(?<onDelete> on delete (?:cascade|set null|set default|restrict|no action))?(?<onUpdate> on update (?:cascade|set null|set default|restrict|no action))?/i',
            $job->createTable,
            $matches,
            PREG_SET_ORDER
        )) {
            return;
        }

        foreach ($matches as $match) {
            $foreignKey = new ForeignKey(
                $match['field'],
                $match['table'],
                $match['reference'],
                $match['onDelete'] !== '' ? $this->getAction($match['onDelete']) : Action::Restrict,
                $match['onUpdate'] !== '' ? $this->getAction($match['onUpdate']) : Action::Restrict,
            );

            $job->blueprint->fieldByName($foreignKey->field)->isIndex = false;

            $job->blueprint->addForeignKey($foreignKey);
        }
    }

    private function getAction(string $string): Action
    {
        if (!preg_match(
            '/ on (?:delete|update) (cascade|set null|set default|restrict|no action)/i',
            $string,
            $match
        )) {
            throw new Exceptions\InvalidForeignKeyAction($string);
        }

        return Action::from(strtolower($match[1]));
    }
}
