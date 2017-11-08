<?php

/*
 * This file is part of the Sil Project.
 *
 * Copyright (C) 2015-2017 Libre Informatique
 *
 * This file is licenced under the GNU GPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Blast\Bundle\OuterExtensionBundle\Entity\Traits;

use Blast\Bundle\CoreBundle\Tools\Reflection\ClassAnalyzer;

trait OuterExtensible
{
    /**
     * If $this has a trait called HasMyEntities, calls $this->initMyEntities.
     */
    protected function initOuterExtendedClasses()
    {
        $traits = ClassAnalyzer::getTraits($this);
        foreach ($traits as $trait) {
            $rc = new \ReflectionClass($trait);
            $method = preg_replace('/^Has/', 'init', $rc->getShortName(), 1);
            if ($method && $method != $rc->getShortName() && ClassAnalyzer::hasMethod($rc, $method)) {
                $this->$method();
            }
        }
    }

    /**
     * When a bidirectional assocation is updated, Doctrine only checks on one of both sides for these changes.
     * This is called the owning side of the association.
     * You have to update the association from the inverse side...
     * http://docs.doctrine-project.org/en/latest/reference/working-with-associations.html#association-management-methods.
     */
    protected function setOwningSideRelation($owning)
    {
        $rc = new \ReflectionClass($this);
        $setter = 'set' . $rc->getShortName();

        $owning_rc = new \ReflectionClass($owning);
        if (ClassAnalyzer::hasMethod($owning_rc, $setter)) {
            $owning->$setter($this);
        }
    }
}
