<?php

namespace KRG\IntlBundle\Twig;

use KRG\CmsBundle\Entity\SeoInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class IntlExtension extends \Twig_Extension
{
    /**
     * @var array
     */
    protected $locales;

    /**
     * @var null|\Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var RouterInterface
     */
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

        $_locale = $this->request->getLocale();

        $routeName = $this->request->get('_route');
        $routeParams = $this->request->get('_route_params');

        $_seo = $this->request->get('_seo');
        if ($_seo instanceof SeoInterface) {
            $routeName = $_seo->getUid();
            $routeParams = [];
        }

        /* clean route parameters */
        foreach ($routeParams as $key => $value) {
            if (preg_match('/^_/', $key)) {
                unset($routeParams[$key]);
            }
        }

        foreach ($this->locales as $locale) {

            $routeParams = array_merge($routeParams, ['_locale' => $locale]);

            $nodes[] = [
                'route'  => [
                    'name'   => $routeName,
                    'params' => $routeParams,
                ],
                'name'   => $locale,
                'icon'  => 'icon-flag-' . $locale,
                'locale' => $locale,
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
