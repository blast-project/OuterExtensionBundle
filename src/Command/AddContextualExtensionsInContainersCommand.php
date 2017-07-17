<?php

/*
 * This file is part of the Blast Project package.
 *
 * Copyright (C) 2015-2017 Libre Informatique
 *
 * This file is licenced under the GNU LGPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Blast\OuterExtensionBundle\Command;

use Blast\OuterExtensionBundle\Command\Traits\Interaction;
use Blast\OuterExtensionBundle\Tools\Reflection\ClassAnalyzer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\ClassLoader\ClassMapGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddContextualExtensionsInContainersCommand extends ContainerAwareCommand
{
    use Interaction;

    protected $count = 0;
    protected $dir;
    protected $output;

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('blast:add:contextual-extension')
            ->setDescription('Adds a given extension in the Extensions Container of an Entity if it already has an other given Extension Provider (trait)')
            ->addOption('dir', 'd', InputOption::VALUE_OPTIONAL, 'The namespace root where Extension Containers will be patched ex: "src", "vendor/acme"')
            ->addArgument('source', InputArgument::REQUIRED, 'The searched Extension Provider or Interface, with its fully-qualified namespace')
            ->addArgument('destination', InputArgument::REQUIRED, 'The Extension Provider to add into the Extension Container, with its fully-qualified namespace')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->dir = $input->getOption('dir');
        $mapping = [];

        foreach ($this->getBundles() as $bundle) {
            $mapping += ClassMapGenerator::createMap($bundle->getPath());
        }

        foreach ($mapping as $class => $path) {
            if ($this->isNormalEntity($class)) {
                require_once $path;
                $ca = new ClassAnalyzer($class);

                if ($ca->isTrait() || $ca->isInterface()) {
                    continue;
                }
            // preconditions
            if (!$ca->hasTrait($this->convertNamespace($input->getArgument('source')))
                && !$ca->implementsInterface($this->convertNamespace($input->getArgument('source')))) {
                continue;
            }
                if ($ca->hasTrait($this->convertNamespace($input->getArgument('destination')))) {
                    continue;
                }

            // finds out the Extension Container of the current entity
            foreach ($ca->getTraits() as $traitName => $trait) {
                $cat = new ClassAnalyzer($trait);
                if (!(
                    $cat->isInsideNamespace('AppBundle\\Entity\\OuterExtension')
                 && $cat->hasSuffix('Extension')
                )) {
                    continue;
                }

                $this->insertUseTraitInPHPCode(
                    '\\' . $this->convertNamespace($input->getArgument('destination')),
                    $cat->getFilename()
                );
                echo $cat->getFilename() . PHP_EOL;
            }
            }
        }

        /*
        if ( $this->count < 1 )
            $this->output->writeln('No missing traits were found');
        */

        return 0;
    }

    protected static function insertUseTraitInPHPCode($FQTraitName, $filePath)
    {
        if (!is_writable($filePath)) {
            throw new \Symfony\Component\Filesystem\Exception\IOException(sprintf('The target file is unwritable. File: %s', $cat->getFilename()));
        }
        $content = preg_replace(
            '!(<\?php\s*namespace\s+\w.*;\s*trait\s+\w.*[\s*\n*]{)!',
            "\\1\n    use $FQTraitName;",
            file_get_contents($filePath)
        );
        file_put_contents(
            $filePath,
            $content
        );
    }

    protected function convertNamespace($namespace)
    {
        return str_replace('/', '\\', $namespace);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if (!$input->getOption('dir')) {
            $questionHelper->writeSection($output, 'Welcome to the Blast extension container generator');
        }

        if (!$input->getOption('dir')) {
            $dir = $this->askAndValidate(
                $input, $output, 'The source folder of your "AppBundle" where traits will be generated in Entity\OuterExtension\{BundleName}', 'src/'
            );

            $input->setOption('dir', $dir);
        }
    }

    protected function getBundle($name)
    {
        $bundles = $this->getApplication()->getKernel()->getBundles();

        if (isset($bundles[$name])) {
            return $bundles[$name];
        }

        throw new \RuntimeException("There is no bundle named $name.");
    }

    protected function getBundles()
    {
        return $this->getApplication()->getKernel()->getBundles();
    }

    /**
     * Returns true if the given class seems to be an entity
     * basing the analysis on its namespace.
     *
     * @param string $class The name of the class
     *
     * @return bool
     */
    public static function isNormalEntity($class)
    {
        return strpos($class, '\\Entity\\') !== false
            && strpos($class, '\\Tests\\') === false
        ;
    }

    /**
     * Returns if the given class seems to be a trait
     * basing the analysis on its namespace.
     *
     * @param string $class The name of the class
     *
     * @return bool
     */
    public function isNormalTrait($class)
    {
        return strpos($class, '\\OuterExtension\\') !== false && strpos($class, '\\Tests\\') === false;
    }
}
