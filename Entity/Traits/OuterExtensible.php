<?php

namespace Librinfo\OuterExtensionBundle\Entity\Traits;

use Librinfo\CoreBundle\Tools\Reflection\ClassAnalyzer;

trait OuterExtensible
{
    /**
     * @var array
     */
    protected $externallyLinkedClasses = [];

    /**
     * @return array
     */
    public static function getExternallyLinkedClasses()
    {
        $instance = new self();
        return $instance->externallyLinkedClasses;
    }

    /**
     * If $this has a trait called HasMyEntities, calls $this->initMyEntities
     */
    protected function initExternallyLinkedClasses()
    {
        $traits = ClassAnalyzer::getTraits($this);
        foreach ( $traits as $trait ) {
            $rc = new \ReflectionClass($trait);
            $method = preg_replace('/^Has/', 'init', $rc->getShortName(), 1);
            if ($method && $method != $rc->getShortName() && ClassAnalyzer::hasMethod($rc, $method))
                $this->$method();
        }

    }
}