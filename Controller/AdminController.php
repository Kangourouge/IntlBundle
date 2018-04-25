<?php

namespace KRG\IntlBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EMC\FileinputBundle\Form\Type\FileinputType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/intl", name="krg_intl_admin_")
 */
class AdminController extends BaseAdminController
{
    const TYPE_CMS = 'cms';
    const TYPE_ENTITY = 'entity';

    /**
     * @Route("/edit", name="edit")
     */
    public function editAction()
    {
        $form = $this->createFormBuilder()
            ->add('file', FileinputType::class)
            ->add('type', ChoiceType::class, [
                'choices'  => [
                    self::TYPE_CMS    => self::TYPE_CMS,
                    self::TYPE_ENTITY => self::TYPE_ENTITY,
                ],
                'expanded' => true,
            ])
            ->add('submit', SubmitType::class, ['label' => 'action.save'])
            ->getForm();

        return $this->render('KRGIntlBundle:Admin:edit.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/download/{type}", name="download", requirements={"type"="cms|entity"})
     */
    public function downloadAction($type)
    {
        return new Response();
    }
}