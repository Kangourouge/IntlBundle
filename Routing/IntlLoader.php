<?php

namespace KRG\IntlBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class IntlLoader extends Loader
{
    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var array
     */
    private $locales;

    /**
     * IntlLoader constructor.
     *
     * @param string $defaultLocale
     * @param array $locales
     */
    public function __construct(string $defaultLocale, array $locales)
    {
        $this->defaultLocale = $defaultLocale;
        $this->locales = $locales;
    }

    public function load($resource, $type = null)
    {
        $collection = new RouteCollection();

        $baseCollection = $this->import($resource);

        $regexpLocales = implode('|', $this->locales);

        /** @var Route $route */
        /** @var Route $routeClone */
        /** @var Route $routeRedirect */
        foreach($baseCollection as $name => $route) {
            if (preg_match('/^_|liip/', $name)) {
                $collection->add($name, $route);
                continue;
            }

            $routeClone = clone $route;
            $routeClone->setPath(sprintf('/{_locale}%s', $routeClone->getPath()));
            $routeClone->addRequirements(['_locale' => $regexpLocales]);
            $routeClone->setDefault('_locale', $this->defaultLocale);
            $collection->add($name, $routeClone);

            $routeRedirect = new Route($route->getPath(), [
                '_controller' => 'FrameworkBundle:Redirect:urlRedirect',
                'path' => sprintf('/%s%s', $this->defaultLocale, $route->getPath()),
                'permanent' => true
            ]);

            $collection->add(sprintf('%s_redirect', $name), $routeRedirect);
        }

        return $collection;
    }

    public function supports($resource, $type = null)
    {
        return $type === 'intl';
    }
}