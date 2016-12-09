<?php

/*
 * This file is part of the BLAST package <http://blast.libre-informatique.fr>.
 *
 * Copyright (C) 2015-2016 Libre Informatique
 *
 * This file is licenced under the GNU GPL v3.
 * For the full copyright and license information, please view the LICENSE
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
            ->addArgument('source', InputArgument::REQUIRED, 'The searched Extension Provider, with its fully-qualified namespace')
            ->addArgument('destination', InputArgument::REQUIRED, 'The Extension Provider to add into the Extensions Container, with its fully-qualified namespace')
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

        foreach ( $this->getBundles() as $bundle )
            $mapping += ClassMapGenerator::createMap($bundle->getPath());

        foreach ( $mapping as $class => $path )
        if ( $this->isNormalEntity($class) )
        {
            require_once $path;
            $rc = new ClassAnalyzer($class);
        }

        /*
        if ( $this->count < 1 )
            $this->output->writeln('No missing traits were found');
        */

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ( !$input->getOption('dir') )
            $questionHelper->writeSection($output, 'Welcome to the Blast extension container generator');

        if ( !$input->getOption('dir') )
        {
            $dir = $this->askAndValidate(
                $input, $output, 'The source folder of your "AppBundle" where traits will be generated in Entity\OuterExtension\{BundleName}', 'src/'
            );

            $input->setOption('dir', $dir);
        }
    }

    protected function getBundle($name)
    {
        $bundles = $this->getApplication()->getKernel()->getBundles();

        if ( isset($bundles[$name]) )
            return $bundles[$name];

        throw new \RuntimeException("There is no bundle named $name.");
    }

    protected function getBundles()
    {
        return $this->getApplication()->getKernel()->getBundles();
    }

    /**
     * Returns if the given class seems to be an entity
     * basing the analysis on its namespace
     *
     * @param string $class The name of the class
     * @return boolean
     */
    public function isNormalEntity($class)
    {
        return strpos($class, '\\Entity\\') !== false && strpos($class, '\\Tests\\') === false;
    }

    /**
     * Returns if the given class seems to be a trait
     * basing the analysis on its namespace
     *
     * @param string $class The name of the class
     * @return boolean
     */
    public function isNormalTrait($class)
    {
        return strpos($class, '\\OuterExtension\\') !== false && strpos($class, '\\Tests\\') === false;
    }
}
