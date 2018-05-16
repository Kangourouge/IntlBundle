<?php

namespace KRG\IntlBundle\Routing;

use KRG\CmsBundle\Routing\RoutingLoaderInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class IntlLoader extends Loader implements RoutingLoaderInterface
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
        $collection = $this->import($resource);

        return $this->handle($collection);
    }

    public function handle(RouteCollection $collection)
    {
        try {
            $_collection = new RouteCollection();

            $regexpLocales = implode('|', $this->locales);

            /** @var Route $route */
            /** @var Route $routeClone */
            /** @var Route $routeRedirect */
            foreach($collection as $name => $route) {
                if (preg_match('/^_|liip|krg_user_logout/', $name) || preg_match('/\{_locale\}/', $route->getPath())) {
                    $_collection->add($name, $route);
                    continue;
                }

                $routeClone = clone $route;
                $routeClone->setPath(sprintf('/{_locale}%s', $routeClone->getPath()));
                $routeClone->addRequirements(['_locale' => $regexpLocales]);
                $routeClone->setDefault('_locale', $this->defaultLocale);
                $_collection->add($name, $routeClone);

                $routeRedirect = new Route($route->getPath(), [
                    '_controller' => 'FrameworkBundle:Redirect:redirect',
                    'permanent' => true,
                    'route' => $name
                ]);

                $_collection->add(sprintf('%s_redirect', $name), $routeRedirect);
            }

            return $_collection;
        } catch (\Exception $exception) {
        }

        return $collection;
    }

    public function supports($resource, $type = null)
    {
        return 'intl' === $type;
    }
}