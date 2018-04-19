<?php

namespace KRG\IntlBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

abstract class AbstractTranslationType extends AbstractType
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var array
     */
    private $locales;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * AbstractTranslationType constructor.
     * @param EntityManagerInterface $entityManager
     * @param array $locales
     * @param string $defaultLocale
     */
    public function __construct(EntityManagerInterface $entityManager, array $locales, $defaultLocale)
    {
        $this->entityManager = $entityManager;
        $this->locales = $locales;
        $this->defaultLocale = $defaultLocale;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (count($this->locales) === 0) {
            return;
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, [$this, 'onPostSetData']);
        $builder->addModelTransformer(new CallbackTransformer(
            function($value) { return ['translations' => $value]; },
            function($value) { return $value['translations']; }
        ));

        parent::buildForm($builder, array_merge($options, ['data' => null]));
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if (count($this->locales) === 0) {
            return;
        }

        parent::finishView($view, $form, $options);
        $rootFormView = $this->getRootFormView($view);
        $rootFormView->vars['locales'] = $this->locales;
        $rootFormView->vars['default_locale'] = $this->defaultLocale;
    }

    private function getRootFormView(FormView $view) {
        return $view->parent === null ? $view : $this->getRootFormView($view->parent);
    }

    public function getParent()
    {
        if (count($this->locales) === 0) {
            return $this->getExtendedType();
        }

        return parent::getParent();
    }

    public function onPostSetData(FormEvent $event)
    {
        $form = $event->getForm();

        $form->add('translations', TranslationCollectionType::class, [
            'entry_type' => $this->getExtendedType(),
            'entry_options' => [
                'label' => false
            ],
            'field' => $form->getName(),
            'entity' => $form->getParent()->getData(),
            'label' => false,
        ]);
    }

    abstract public function getExtendedType();
}