<?php

namespace KRG\IntlBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class TextTranslationType extends AbstractTranslationType
{
    public function getExtendedType()
    {
        return TextType::class;
    }
}