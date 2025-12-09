<?php

declare(strict_types=1);

namespace Fabiang\Doctrine\Migrations\Liquibase;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool as DoctrineSchemaTool;
use DOMDocument;
use Fabiang\Doctrine\Migrations\Liquibase\Output\DOMDocumentOutput;
use Fabiang\Doctrine\Migrations\Liquibase\Output\OutputInterface;

use function strcmp;
use function usort;

/**
 * @psalm-suppress UnusedClass
 */
class SchemaTool extends DoctrineSchemaTool
{
    private Options $options;

    public function __construct(
        private EntityManagerInterface $em,
        ?Options $options = null
    ) {
        parent::__construct($em);

        if ($options === null) {
            $options = new Options();
        }

        $this->options = $options;
    }

    /**
     * Generate a diff changelog from differences between actual database state and doctrine metadata.
     *
     * @throws ORMException
     */
    public function diffChangeLog(
        ?OutputInterface $output = null,
        ?array $metadata = null
    ): DOMDocument {
        $soutput   = $this->sanitizeOutputParameter($output);
        $smetadata = $this->sanitizeMetadatas($metadata);
        $platform  = $this->em->getConnection()->getDatabasePlatform();

        $sm = $this->em->getConnection()->createSchemaManager();

        $fromSchema = $sm->introspectSchema();
        $this->removeLiquibaseTables($fromSchema);
        $toSchema = $this->getSchemaFromMetadata($smetadata);

        /**
         * @psalm-suppress InternalMethod Comparator is marked as internal, but we need it
         */
        $comparator = new Comparator($platform);
        $schemaDiff = $comparator->compareSchemas($fromSchema, $toSchema);

        return $this->diffChangeLogFromSchemaDiff($schemaDiff, $soutput);
    }

    /**
     * Generate a full changelog from doctrine metadata.
     *
     * @throws ORMException
     */
    public function changeLog(
        ?OutputInterface $output = null,
        ?array $metadata = null
    ): DOMDocument {
        $soutput   = $this->sanitizeOutputParameter($output);
        $smetadata = $this->sanitizeMetadatas($metadata);
        $toSchema  = $this->getSchemaFromMetadata($smetadata);
        $platform  = $this->em->getConnection()->getDatabasePlatform();

        $fromSchema = new Schema();
        /**
         * @psalm-suppress InternalMethod Comparator is marked as internal, but we need it
         */
        $comparator = new Comparator($platform);
        $schemaDiff = $comparator->compareSchemas($fromSchema, $toSchema);

        return $this->diffChangeLogFromSchemaDiff($schemaDiff, $soutput);
    }

    /**
     * Generate a diff changelog from SchemaDiff object.
     */
    public function diffChangeLogFromSchemaDiff(
        SchemaDiff $schemaDiff,
        ?OutputInterface $output = null
    ): DOMDocument {
        $soutput = $this->sanitizeOutputParameter($output);

        $soutput->started($this->em);

        foreach ($schemaDiff->getCreatedSchemas() as $newNamespace) {
            $soutput->createSchema($newNamespace);
        }

        foreach ($schemaDiff->getAlteredSequences() as $sequence) {
            $soutput->alterSequence($sequence);
        }

        foreach ($schemaDiff->getDroppedSequences() as $sequence) {
            $soutput->dropSequence($sequence);
        }

        foreach ($schemaDiff->getCreatedSequences() as $sequence) {
            $soutput->createSequence($sequence);
        }

        foreach ($schemaDiff->getCreatedTables() as $table) {
            $soutput->createTable($table);

            foreach ($table->getForeignKeys() as $foreignKey) {
                $soutput->createForeignKey($foreignKey, $table);
            }
        }

        foreach ($schemaDiff->getDroppedTables() as $table) {
            $soutput->dropTable($table);
        }

        foreach ($schemaDiff->getAlteredTables() as $tableDiff) {
            $soutput->alterTable($tableDiff);

            foreach ($tableDiff->getDroppedForeignKeys() as $foreignKey) {
                $oldTable = $tableDiff->getOldTable();
                /**
                 * @psalm-suppress RedundantCondition
                 */
                if ($foreignKey instanceof ForeignKeyConstraint && $oldTable !== null) {
                    $soutput->dropForeignKey($foreignKey, $oldTable);
                }
            }
        }

        $soutput->terminated();

        return $soutput->getResult();
    }

    private function removeLiquibaseTables(Schema $fromSchema): void
    {
        $tables = $this->options->getIgnoreTables();

        foreach ($tables as $table) {
            if ($fromSchema->hasTable($table)) {
                $fromSchema->dropTable($table);
            }
        }
    }

    private function sanitizeOutputParameter(?OutputInterface $output = null): OutputInterface
    {
        if ($output instanceof OutputInterface) {
            return $output;
        }

        return new DOMDocumentOutput($this->options);
    }

    /**
     * @return list<ClassMetadata<object>>
     */
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
}
