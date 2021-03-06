<?php

namespace KRG\IntlBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Translatable;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use KRG\IntlBundle\Form\DataTransformer\TranslationDataTransformer;

class TranslationCollectionType extends AbstractType
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var TranslatableListener */
    private $translatableListener;

    /** @var array */
    private $locales;

    /** @var string */
    private $defaultLocale;

    public function __construct(EntityManagerInterface $entityManager, TranslatableListener $translatableListener, array $locales, $defaultLocale)
    {
        $this->entityManager = $entityManager;
        $this->translatableListener = $translatableListener;
        $this->locales = $locales;
        $this->defaultLocale = $defaultLocale;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $dataTransformer = new TranslationDataTransformer($this->entityManager, $this->translatableListener, $this->locales, $this->defaultLocale, $options['entity'], $options['field']);
        $builder->addModelTransformer($dataTransformer);

        foreach ($this->locales as $locale) {
            $builder->add($locale, $options['entry_type'], array_merge(
                $options['entry_options'],
                $locale !== $this->defaultLocale ? ['required' => false] : [],
                ['attr' => ['lang' => $locale, 'default_locale' => $this->defaultLocale]],
                ['label' => $options['label']]
            ));
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setRequired('entry_type');
        $resolver->setRequired('entry_options');
        $resolver->setRequired('entity');
        $resolver->setRequired('field');
        $resolver->setAllowedTypes('entity', Translatable::class);
        $resolver->setAllowedTypes('entry_type', 'string');
        $resolver->setAllowedTypes('entry_options', 'array');
        $resolver->setAllowedTypes('field', 'string');
    }
}
