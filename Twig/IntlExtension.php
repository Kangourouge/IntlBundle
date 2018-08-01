<?php

namespace KRG\IntlBundle\Twig;

use KRG\CmsBundle\Entity\SeoInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class IntlExtension extends \Twig_Extension
{
    /** @var array */
    protected $locales;

    /** @var null|\Symfony\Component\HttpFoundation\Request */
    protected $request;

    /** @var RouterInterface */
    protected $router;

    public function __construct(RequestStack $requestStack, RouterInterface $router, array $locales)
    {
        $this->request = $requestStack->getMasterRequest();
        $this->router = $router;
        $this->locales = $locales;
    }

    public function render(\Twig_Environment $environment, $theme = 'KRGIntlBundle::bootstrap.html.twig')
    {
        $nodes = [];

        $routeName = $this->request->get('_route');
        if (!$routeName) {
            return;
        }
        $locale = $this->request->getLocale();
        $routeParams = $this->request->get('_route_params') ?: [];
        $_seo = $this->request->get('_seo');
        if ($_seo instanceof SeoInterface and $_seo->getRouteName() === 'krg_page_show') {
            $routeName = $_seo->getUid();
            $routeParams = [];
        }

        // Get unlocalized route
        $suffixes = array_merge($this->locales, ['locale']);
        foreach ($suffixes as $suffix) {
            if (strstr($routeName, '.'.$suffix)) {
                $routeName = str_replace('.'.$suffix, '', $routeName);
                break;
            }
        }

        // Clear route parameters
        foreach ($routeParams as $key => $value) {
            if (preg_match('/^_/', $key)) {
                unset($routeParams[$key]);
            }
        }

        // Get additonal parameters from request
        $route = $this->router->getRouteCollection()->get($routeName);
        $routeVariables = $route->compile()->getVariables();
        foreach ($routeVariables as $variable) {
            if ($this->request->attributes->has($variable)) {
                $routeParams[$variable] = $this->request->attributes->get($variable);
            }
        }

        foreach ($this->locales as $_locale) {
            $routeParams = array_merge($routeParams, ['_locale' => $_locale]);
            $nodes[] = [
                'route'  => [
                    'name'   => $routeName,
                    'params' => $routeParams,
                ],
                'name'   => $_locale,
                'icon'  => 'icon-flag-' . $_locale,
                'locale' => $_locale,
                'url'    => $this->router->generate($routeName, $routeParams),
                'roles'  => [],
                'active' => $_locale === $locale,
            ];
        }

        $template = $environment->load($theme);

        return $template->renderBlock('intl', [
            'id'    => uniqid('krg_intl_'),
            'nodes' => $nodes,
        ]);
    }

    public function getFunctions()
    {
        return [
            'krg_intl' => new \Twig_SimpleFunction('krg_intl', [$this, 'render'], [
                'needs_environment' => true,
                'is_safe'           => ['html'],
            ]),
        ];
    }
}
