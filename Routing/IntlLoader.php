<?php

namespace KRG\IntlBundle\Routing;

use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use KRG\CmsBundle\Entity\SeoInterface;
use KRG\CmsBundle\Routing\RoutingLoaderInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

class IntlLoader extends Loader implements RoutingLoaderInterface
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var Serializer */
    private $serializer;

    /** @var string */
    private $defaultLocale;

    /** @var array */
    private $locales;

    /** @var string */
    private $seoClass;

    /** @var array */
    private $routes;

    public function __construct(EntityManagerInterface $entityManager, string $defaultLocale, array $locales)
    {
        $this->entityManager = $entityManager;
        $this->serializer = new Serializer([new PropertyNormalizer()], [new JsonEncoder()]);
        $this->defaultLocale = $defaultLocale;
        $this->locales = $locales;
        $this->seoClass = $this->entityManager->getMetadataFactory()->getMetadataFor(SeoInterface::class)->getName();
        $this->routes = [];
    }

    public function load($resource, $type = null)
    {
        $collection = $this->import($resource);

        return $this->handle($collection);
    }

    public function handle(RouteCollection $collection)
    {
        /** @var Route $route */
        foreach ($collection as $name => $route) {
            if (preg_match('/^_|liip|admin|krg_user_logout/', $name) || preg_match('/\{_locale\}/', $route->getPath())) {
                continue;
            }

            $route->setDefault('_locale', $this->defaultLocale); // All routes default locale
            $missingLocales = $this->processTranslatedRoutes($route, $name);

            foreach ($missingLocales as $locale) {
                $url = sprintf('/%s%s', $locale, $route->getPath());
                $defaults = ['_canonical_route' => $name, '_locale' => $locale];
                $requirements = ['_locale' => $locale];
                $this->routes[$name.'.'.$locale] = $this->cloneRoute($route, $url, $defaults, $requirements);
            }
        }

        $intlCollection = new RouteCollection();
        foreach ($this->routes as $name => $route) {
            $intlCollection->add($name, $route);
        }

        return $intlCollection;
    }

    /**
     * Find alternative route paths from Seo entities
     */
    protected function processTranslatedRoutes(Route $route, string $name)
    {
        $translatableRepository = $this->entityManager->getRepository(Translation::class);
        $seoRepository = $this->entityManager->getRepository(SeoInterface::class);
        $locales = array_combine($this->locales, $this->locales);

        // Do not rewritte Page twice
        if ($name === 'krg_page_show') {
            return $locales;
        }

        $seos = [];
        if ($route->hasDefault('_seo_list')) {
            $seos = $route->getDefault('_seo_list');
        } elseif ($route->hasDefault('_seo_id')) {
            $seos = [$seoRepository->find($route->getDefault('_seo_id'))];
        }

        foreach ($seos as $seo) {
            if (is_string($seo)) {
                $seo = $this->serializer->deserialize($seo, $this->seoClass, 'json');
            }

            // Find custom Seo urls by locale
            if ($translations = $translatableRepository->findTranslations($seo)) {
                foreach ($this->locales as $locale) {
                    if (($url = ($translations[$locale]['url'] ?? null)) && $url !== $route->getPath()) {
                        $defaults = ['_canonical_route' => $name, '_locale' => $locale, '_seo_id' =>  $seo->getId()];
                        $requirements = ['_locale' => $locale];
                        $localizedRoute = $this->cloneRoute($route, $url, $defaults, $requirements);

                        $path = sprintf('/%s%s', $locale, $route->getPath());
                        $this->routes[$name.'.'.$locale.'.redirect'] = $this
                            ->cloneRoute($localizedRoute, $path)
                            ->setDefaults([
                                '_controller' => 'FrameworkBundle:Redirect:redirect',
                                'route'       => $name.'.'.$locale,
                                '_locale'     => $locale,
                                'permanent'   => true,
                            ])
                            ->setRequirements(['_locale' => $locale]);

                        $this->routes[$name.'.'.$locale] = $localizedRoute;

                        unset($locales[$locale]); // Localized route successfuly created
                    }
                }
            }
        }

        // Create localized for default locale only if translated
        unset($locales[$this->defaultLocale]);

        return $locales;
    }

    public function cloneRoute(Route $route, $path = null, array $defaults = [], array $requirements = [])
    {
        $clonedRoute = clone $route;

        if ($path) {
            if ($path[strlen($path) - 1] === '/') {
                $path = substr($path, 0, -1);
            }
            $clonedRoute->setPath($path);
        }
        $clonedRoute->addDefaults($defaults);
        $clonedRoute->addRequirements($requirements);

        return $clonedRoute;
    }

    public function supports($resource, $type = null)
    {
        return 'intl' === $type;
    }
}
