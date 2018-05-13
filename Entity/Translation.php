<?php

namespace KRG\IntlBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass(repositoryClass="Gedmo\Translatable\Entity\Repository\TranslationRepository")
 */
class Translation extends \Gedmo\Translatable\Entity\Translation implements TranslationInterface
{
    /**
     * @var string $content
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $foreignTextKey;

    /**
     * @return string
     */
    public function getForeignTextKey()
    {
        return $this->foreignTextKey;
    }

    /**
     * @param string $foreignTextKey
     *
     * @return Translation
     */
    public function setForeignTextKey($foreignTextKey)
    {
        $this->foreignTextKey = $foreignTextKey;

        return $this;
    }
}