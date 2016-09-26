<?php

namespace Librinfo\OuterExtensionBundle\Entity\Traits;

use Librinfo\CoreBundle\Tools\Reflection\ClassAnalyzer;

trait OuterExtensible
{
    /**
     * If $this has a trait called HasMyEntities, calls $this->initMyEntities
     */
    protected function initOuterExtendedClasses()
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