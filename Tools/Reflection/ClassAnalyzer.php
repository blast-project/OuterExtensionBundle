<?php

/*
 *
 * Copyright (C) 2015-2017 Libre Informatique
 *
 * This file is licenced under the GNU LGPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Blast\Bundle\OuterExtensionBundle\Tools\Reflection;

use Blast\Bundle\CoreBundle\Tools\Reflection\ClassAnalyzer as CoreClassAnalyzer;

class ClassAnalyzer
{
    // ReflectionClass
    protected $rc;

    /**
     * @param ReflectionClass|string $class A ReflectionClass object or a class name
     **/
    public function __construct($class)
    {
        $this->rc = $class instanceof \ReflectionClass
        ? $class
        : new \ReflectionClass($class);
    }

    /**
     * Returns all parents of a class (parent, parent of parent, parent of parent's parent and so on).
     *
     * @return array
     */
    public function getAncestors()
    {
        return CoreClassAnalyzer::getAncestors($this->rc);
    }

    /**
     * getTraits.
     *
     * This method returns back all traits used by a given class
     * recursively
     *
     * @return array of (string)traitName => \ReflectionClasses
     */
    public function getTraits()
    {
        return $this->_getTraits($this->rc);
    }

    /**
     * isTrait.
     *
     * This method returns true if the current "class" is a trait
     *
     * @return bool
     */
    public function isTrait()
    {
        return $this->rc->isTrait();
    }

    /**
     * hasTraits.
     *
     * This method returns back all traits used by a given class
     * recursively
     *
     * @param string $traitName A string representing an existing trait
     *
     * @return bool
     */
    public function hasTrait($traitName)
    {
        return CoreClassAnalyzer::hasTrait($this->rc, $traitName);
    }

    /**
     * hasProperty.
     *
     * This method says if a class has a property
     *
     * @param string $propertyName A string representing an existing property
     *
     * @return bool
     */
    public function hasProperty($propertyName)
    {
        return CoreClassAnalyzer::hasProperty($this->rc, $propertyName);
    }

    /**
     * hasMethod.
     *
     * This method says if a class has a method
     *
     * @param string $methodName a method name
     *
     * @return bool
     */
    public function hasMethod($methodName)
    {
        return CoreClassAnalyzer::hasMethod($methodName);
    }

    /**
     * isInsideNamespace.
     *
     * This method says if a class namespace is the namespace or a sub-namespace of the given one
     *
     * @param string $namespace a namespace
     *
     * @return bool
     */
    public function isInsideNamespace($namespace)
    {
        $orig = preg_replace($search = ['!^\\\\!', '!\\\\$!'], ['', ''], $this->rc->getNamespaceName());
        $namespace = preg_replace($search, ['', ''], $namespace);

        return strpos($orig, $namespace) !== false;
    }

    /**
     * hasSuffix.
     *
     * This method says if a class name ends with a given string
     *
     * @param string $suffix a suffix
     *
     * @return bool
     */
    public function hasSuffix($suffix)
    {
        return preg_match(sprintf('!%s$!', $suffix), $this->rc->getShortName()) === 1;
    }

    /**
     * getFilename.
     *
     * This method is a proxy method for ReflectionClass::getFilename()
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->rc->getFilename();
    }

    private static function _getTraits($class, array $traits = null)
    {
        if (is_null($traits)) {
            $traits = array();
        }

        // traits being embedded through the current class or the embedded traits
        foreach ($class->getTraits() as $trait) {
            $traits = self::_getTraits($trait, $traits); // first the embedded traits that come first...
            if (!isset($traits[$trait->name])) {
                $traits[$trait->name] = $trait;
            }                    // then the current trait
        }

        // traits embedded by the parent class
        if ($class->getParentClass()) {
            $traits = self::_getTraits($class->getParentClass(), $traits);
        }

        return $traits;
    }
}
