<?php

/*
 *
 * Copyright (C) 2015-2017 Libre Informatique
 *
 * This file is licenced under the GNU LGPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Blast\Bundle\OuterExtensionBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class OuterExtensionListener implements LoggerAwareInterface, EventSubscriber
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var array
     */
    private $extendedClasses = [];

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array $bundles
     */
    public function setExtendedClasses($bundles)
    {
        // TODO: put Bundles to parse in configuration, so we don't need to parse all bundles
        // TODO: specify driver (yml, xml or php) in configuration for each module
        $this->extendedClasses = [];
        foreach ($bundles as $name => $bundle) {
            $rc = new \ReflectionClass($bundle);
            $bundleDir = dirname($rc->getFileName());
            $outerDir = $bundleDir . '/Resources/config/doctrine/outer-extension';
            foreach (glob($outerDir . '/*.dcm.yml') as $file) {
                $class = str_replace('.', '\\', basename($file, '.dcm.yml'));
                if (!isset($this->extendedClasses[$class])) {
                    $this->extendedClasses[$class] = [];
                }
                $this->extendedClasses[$class][] = dirname($file);
            }
        }
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
        'loadClassMetadata',
        ];
    }

    /**
     * Dynamic Doctrine mappings.
     *
     * @param LoadClassMetadataEventArgs $eventArgs
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        /**
         * @var ClassMetadata
         */
        $metadata = $eventArgs->getClassMetadata();
        $className = $metadata->getName();

        if (!key_exists($className, $this->extendedClasses)) {
            return;
        }

        $this->logger->debug('[OuterExtensionListener] Entering listener for « loadClassMetadata » event', ['class' => $className]);

        foreach ($this->extendedClasses[$className] as $locator) {
            $outMetadata = new ClassMetadata($className);
            // TODO: use different drivers (configuration)
            $driver = new \Doctrine\ORM\Mapping\Driver\YamlDriver($locator, '.dcm.yml');
            $driver->loadMetadataForClass($className, $outMetadata);

            $this->setCustomRepository($metadata, $outMetadata)
                ->setInheritance($metadata, $outMetadata)
                ->addFieldMappings($metadata, $outMetadata)
                ->addAssociationMappings($metadata, $outMetadata)
            // TODO: add here other stuff definable by the ORM
            ;
        }
    }

    /**
     * @param ClassMetadata $metadata
     * @param ClassMetadata $outMetadata
     */
    private function addFieldMappings($metadata, $outMetadata)
    {
        foreach ($outMetadata->fieldMappings as $fieldName => $mapping) {
            // Warning: you can't change the field type
            if ($metadata->hasField($fieldName)) {
                $metadata->setAttributeOverride($fieldName, $mapping);
            } else {
                $metadata->mapField($mapping);
            }
        }

        return $this;
    }

    /**
     * @param ClassMetadata $metadata
     * @param ClassMetadata $outMetadata
     */
    private function addAssociationMappings($metadata, $outMetadata)
    {
        foreach ($outMetadata->getAssociationMappings() as $mapping) {
            if ($metadata->hasAssociation($mapping['fieldName'])) {
                $metadata->setAssociationOverride($mapping['fieldName'], $mapping);
            } else {
                switch ($mapping['type']) {
                case ClassMetadataInfo::ONE_TO_ONE:
                    $metadata->mapOneToOne($mapping);
                    break;
                case ClassMetadataInfo::ONE_TO_MANY:
                    $metadata->mapOneToMany($mapping);
                    break;
                case ClassMetadataInfo::MANY_TO_ONE:
                    $metadata->mapManyToOne($mapping);
                    break;
                case ClassMetadataInfo::MANY_TO_MANY:
                    $metadata->mapManyToMany($mapping);
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * @param ClassMetadata $metadata
     * @param ClassMetadata $outMetadata
     */
    private function setCustomRepository($metadata, $outMetadata)
    {
        if ($repository = $outMetadata->customRepositoryClassName) {
            $metadata->setCustomRepositoryClass($repository);
        }

        return $this;
    }

    /**
     * @param ClassMetadata $metadata
     * @param ClassMetadata $outMetadata
     */
    private function setInheritance($metadata, $outMetadata)
    {
        if (in_array(
            $outMetadata->inheritanceType, [
            ClassMetadataInfo::INHERITANCE_TYPE_JOINED,
            ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE,
            ]
        )
        ) {
            $metadata->setInheritanceType($outMetadata->inheritanceType);
            $metadata->setDiscriminatorColumn($outMetadata->discriminatorColumn);
            $metadata->setDiscriminatorMap($outMetadata->discriminatorMap);
        }

        return $this;
    }
}
