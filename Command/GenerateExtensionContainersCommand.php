<?php

/*
 *
 * Copyright (C) 2015-2017 Libre Informatique
 *
 * This file is licenced under the GNU LGPL v3.
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Blast\Bundle\OuterExtensionBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ClassLoader\ClassMapGenerator;
use Blast\Bundle\CoreBundle\Command\Traits\Interaction;

/**
 * Class GenerateAdminCommand.
 */
class GenerateExtensionContainersCommand extends ContainerAwareCommand
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
            ->setName('blast:generate:extension-containers')
            ->setDescription('Generates missing extension container traits')
            ->addOption('dir', 'd', InputOption::VALUE_OPTIONAL, 'The namespace root where containers will be generated ex: "src", "vendor/acme"');
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

        spl_autoload_register(array($this, 'loadClass'), true, false);

        foreach ($mapping as $class => $path) {
            if ($this->isNormalEntity($class)) {
                include_once $path;
            }
        }

        if ($this->count < 1) {
            $this->output->writeln('No missing traits were found');
        }

        return 0;
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
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     */
    public function loadClass($class)
    {
        if (!isset($this->map[$class]) && $this->isNormalTrait($class)) {
            $path = $this->getFilePathFromClassPath($class);
            $dir = dirname($path);

            if (!is_dir($dir)) {
                if (file_exists($dir)) {
                    throw new \Exception($dir . ' is a file...');
                } else {
                    mkdir($dir, 0755, true);
                }
            }

            $result = file_put_contents(
                $path, sprintf(
                    "<?php\n\nnamespace %s;\n\ntrait %s\n{\n}\n", str_replace('/', '\\', dirname(str_replace('\\', '/', $class))), pathinfo($path)['filename']
                )
            );

            if ($result !== false || $result !== '') {
                ++$this->count;
                $this->output->writeln(sprintf('Generated trait %s', $path));
            }

            $this->map[$class] = $path;
        }

        if (isset($this->map[$class])) {
            include $this->map[$class];
        }
    }

    /**
     * Returns the file path from the class path.
     *
     * @param string $class The name of the class
     *
     * @return string
     */
    public function getFilePathFromClassPath($class)
    {
        return $this->dir . '/' . str_replace('\\', '/', $class) . '.php';
    }

    /**
     * Returns if the given class seems to be an entity
     * basing the analysis on its namespace.
     *
     * @param string $class The name of the class
     *
     * @return bool
     */
    public function isNormalEntity($class)
    {
        return strpos($class, '\\Entity\\') !== false && strpos($class, '\\Tests\\') === false;
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

    /**
     * Finds the path to the file where the class is defined.
     *
     * @param string $class The name of the class
     *
     * @return string|null The path, if found
     */
    public function findFile($class)
    {
        if (isset($this->map[$class])) {
            return $this->map[$class];
        }
    }
}
