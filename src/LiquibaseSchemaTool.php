<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\SchemaTool;
use DOMDocument;

use function strcmp;
use function usort;

class LiquibaseSchemaTool extends SchemaTool
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
        $this->em = $em;
    }

    /**
     * @param LiquibaseOutput|LiquibaseOutputOptions|null $output
     */
    private function sanitizeOutputParameter(?object $output = null): LiquibaseOutput
    {
        if ($output instanceof LiquibaseOutputOptions) {
            return new LiquibaseDOMDocumentOutput($output);
        } elseif ($output instanceof LiquibaseOutput) {
            return $output;
        }
        return new LiquibaseDOMDocumentOutput();
    }

    private function sanitizeMetadatas(?array $metadata = null): array
    {
        if (! $metadata) {
            $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        }
        usort($metadata, function (ClassMetadata $a, ClassMetadata $b) {
            return strcmp($a->getName(), $b->getName());
        });
        return $metadata;
    }

    /**
     * Generate a diff changelog from differences between actual database state and doctrine metadata.
     *
     * @param LiquibaseOutput|LiquibaseOutputOptions|null $output
     * @param array|null $metadata
     * @return DOMDocument|mixed
     * @throws ORMException
     */
    public function diffChangeLog(?object $output = null, ?array $metadata = null)
    {
        $output   = $this->sanitizeOutputParameter($output);
        $metadata = $this->sanitizeMetadatas($metadata);

        $sm = $this->em->getConnection()->getSchemaManager();

        $fromSchema = $sm->createSchema();
        $this->removeLiquibaseTables($fromSchema);
        $toSchema = $this->getSchemaFromMetadata($metadata);

        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($fromSchema, $toSchema);

        return $this->diffChangeLogFromSchemaDiff($schemaDiff, $output);
    }

    /**
     * Generate a full changelog from doctrine metadata.
     *
     * @param LiquibaseOutput|LiquibaseOutputOptions|null $output
     * @param array|null $metadata
     * @return DOMDocument|mixed
     * @throws ORMException
     */
    public function changeLog($output = null, $metadata = null)
    {
        $output   = $this->sanitizeOutputParameter($output);
        $metadata = $this->sanitizeMetadatas($metadata);
        $schema   = $this->getSchemaFromMetadata($metadata);

        $liquibaseVisitor = new LiquibaseSchemaVisitor($output);
        $output->started($this->em);
        $schema->visit($liquibaseVisitor);
        $output->terminated();

        return $output->getResult();
    }

    /**
     * Generate a diff changelog from SchemaDiff object.
     *
     * @param LiquibaseOutput|LiquibaseOutputOptions|null $output
     * @return DOMDocument|mixed
     */
    public function diffChangeLogFromSchemaDiff(SchemaDiff $schemaDiff, ?object $output = null)
    {
        $output = $this->sanitizeOutputParameter($output);

        $output->started($this->em);

        foreach ($schemaDiff->newNamespaces as $newNamespace) {
            $output->createSchema($newNamespace);
        }

        foreach ($schemaDiff->orphanedForeignKeys as $orphanedForeignKey) {
            $output->dropForeignKey($orphanedForeignKey, $orphanedForeignKey->getLocalTable());
        }

        foreach ($schemaDiff->changedSequences as $sequence) {
            $output->alterSequence($sequence);
        }

        foreach ($schemaDiff->removedSequences as $sequence) {
            $output->dropSequence($sequence);
        }

        foreach ($schemaDiff->newSequences as $sequence) {
            $output->createSequence($sequence);
        }

        foreach ($schemaDiff->newTables as $table) {
            $output->createTable($table);

            foreach ($table->getForeignKeys() as $foreignKey) {
                $output->createForeignKey($foreignKey, $table);
            }
        }

        foreach ($schemaDiff->removedTables as $table) {
            $output->dropTable($table);
        }

        foreach ($schemaDiff->changedTables as $tableDiff) {
            $output->alterTable($tableDiff);
        }

        $output->terminated();

        return $output->getResult();
    }

    private function removeLiquibaseTables(Schema $fromSchema): void
    {
        // TODO: Make those table names configurable
        if ($fromSchema->hasTable('liquibase')) {
            $fromSchema->dropTable('liquibase');
        }
        if ($fromSchema->hasTable('liquibase_lock')) {
            $fromSchema->dropTable('liquibase_lock');
        }
    }
}
