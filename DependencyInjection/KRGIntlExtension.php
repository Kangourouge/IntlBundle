<?php

namespace KRG\IntlBundle\DependencyInjection;

use KRG\IntlBundle\Controller\AdminController;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class KRGIntlExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load($config['legacy'] ? 'services_legacy.yml' : 'services.yml');

        $container->setParameter('krg_intl_locales', $config['locales']);
        $container->setParameter('krg_intl_cache_dir', $config['cache_dir']);

        // Get all app translations files
        $domains = array_merge(['messages'], $config['domains']);
        $translationDir = $container->getParameter('kernel.root_dir').'/Resources/translations';
        if (is_dir($translationDir)) {
            $finder = new Finder();
            foreach($finder->name('*.yml')->in($translationDir) as $file) {
                $domains[] = explode('.', $file->getFilename())[0];
            }
        }
        $domains = array_unique($domains);

        if (!$config['legacy']) {
            $cacheDir = preg_replace('/%kernel\.cache_dir%/', $container->getParameter('kernel.cache_dir'), $config['cache_dir']);

            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);

                $bundleNames = array_keys($container->getParameter('kernel.bundles'));
                foreach ($domains as $domain) {
                    array_push($bundleNames, $domain);
                }

                foreach ($bundleNames as $bundleName) {
                    foreach ($config['locales'] as $locale) {
                        $filename = sprintf('%s/%s.%s.db', $cacheDir, $bundleName, $locale);
                        touch($filename);
                    }
                }
            }
        }

        if (!class_exists('\EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController')) {
            $container->removeDefinition(AdminController::class);
        }
    }
}
