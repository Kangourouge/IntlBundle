<?php

namespace KRG\IntlBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class TextareaTranslationType extends AbstractTranslationType
{
    public function getExtendedType()
    {
        return TextareaType::class;
    }
}