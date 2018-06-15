<?php

namespace KRG\IntlBundle\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;
use Gedmo\Translatable\Translatable;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\Form\DataTransformerInterface;

class TranslationDataTransformer implements DataTransformerInterface
{
    /** @var TranslatableListener */
    private $translatableListener;

    /** @var TranslationRepository */
    private $repository;

    /** @var Translatable */
    private $entity;

    /** @var string */
    private $field;

    /** @var array */
    private $locales;

    /** @var string */
    private $defaultLocale;

    public function __construct(EntityManagerInterface $entityManager, TranslatableListener $translatableListener, array $locales, $defaultLocale, Translatable $entity = null, $field = null)
    {
        /** @var TranslationRepository $repository */
        $this->repository = $entityManager->getRepository(Translation::class);
        $this->translatableListener = $translatableListener;
        $this->entity = $entity;
        $this->field = $field;
        $this->locales = $locales;
        $this->defaultLocale = $defaultLocale;
        $this->translatableListener->setTranslatableLocale($this->defaultLocale);
        $this->translatableListener->setPersistDefaultLocaleTranslation(true);
    }

    public function getDefaultData()
    {
        $data = [$this->defaultLocale => null];
        foreach ($this->locales as $locale) {
            if ($locale !== $this->defaultLocale) {
                $data[$locale] = null;
            }
        }

        return $data;
    }

    public function transform($value)
    {
        $data = $this->getDefaultData();

        $translations = [];
        if ($this->entity && $this->field && count($this->locales) > 1) {
            $_tanslations = $this->repository->findTranslations($this->entity);
            $translations = array_map(function(array $data){ return $data[$this->field] ?? null; }, $_tanslations);
        }

        return array_merge($data, $translations, [$this->defaultLocale => $value]);
    }

    public function reverseTransform($data)
    {
        if (count($this->locales) > 1) {
            $this->translatableListener->setDefaultLocale($this->defaultLocale);
            $this->translatableListener->setPersistDefaultLocaleTranslation(true);

            foreach ($data as $locale => $value) {
                if ($locale != $this->defaultLocale) {
                    $this->repository->translate($this->entity, $this->field, $locale, $value);
                }
            }
        }

        return $data[$this->defaultLocale];
    }
}
