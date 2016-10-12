<?php

namespace Librinfo\OuterExtensionBundle\EventListener;

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
     * @var array
     */
    private $kernelBundles = [];

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array $bundles
     */
    public function setKernelBundles($bundles)
    {
        $this->kernelBundles = $bundles;
        $this->getExtendedClasses();
    }

    public function getExtendedClasses()
    {
        // TODO: put Bundles to parse in configuration, so we don't need to parse all bundles
        // TODO: specify driver (yml, xml or php) in configuration for each module
        $this->extendedClasses = [];
        foreach($this->kernelBundles as $name => $bundle) {
            $rc = new \ReflectionClass($bundle);
            $bundleDir = dirname($rc->getFileName());
            $outerDir = $bundleDir . '/Resources/config/doctrine/outer';
            foreach (glob($outerDir . '/*.dcm.yml') as $file) {
                $class = str_replace('.', '\\', basename($file, '.dcm.yml'));
                if (!isset($this->extendedClasses[$class]))
                    $this->extendedClasses[$class] = [];
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
     * Dynamic Doctrine mappings
     *
     * @param LoadClassMetadataEventArgs $eventArgs
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $eventArgs->getClassMetadata();
        $className = $metadata->getName();

        if (!key_exists($className, $this->extendedClasses))
            return;

        $locator = $this->extendedClasses[$className];
        $outMetadata = new ClassMetadata($className);
        // TODO: use different drivers (configuration)
        $driver = new \Doctrine\ORM\Mapping\Driver\YamlDriver($locator, '.dcm.yml');
        $driver->loadMetadataForClass($className, $outMetadata);

        $this->setCustomRepository($metadata, $outMetadata);
        $this->addFieldMappings($metadata, $outMetadata);
        $this->addAssociationMappings($metadata, $outMetadata);
    }



    /**
     * @param ClassMetadata $metadata
     * @param ClassMetadata $outMetadata
     */
    private function addFieldMappings($metadata, $outMetadata)
    {
        foreach($outMetadata->fieldMappings as $fieldName => $mapping)
        {
            // Warning: you can't change the field type
            if ($metadata->hasField($fieldName))
                $metadata->setAttributeOverride($fieldName, $mapping);
            else
                $metadata->mapField($mapping);
        }
    }

    /**
     * @param ClassMetadata $metadata
     * @param ClassMetadata $outMetadata
     */
    private function addAssociationMappings($metadata, $outMetadata)
    {
        // TODO: if field is already mapped then remove the exsiting mapping before overwriting it
        foreach($outMetadata->getAssociationMappings() as $mapping)
        {
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

    /**
     * @param ClassMetadata $metadata
     * @param ClassMetadata $outMetadata
     */
    private function setCustomRepository($metadata, $outMetadata)
    {
        if ($repository = $outMetadata->customRepositoryClassName) {
            $metadata->setCustomRepositoryClass($repository);
        }
    }


}
