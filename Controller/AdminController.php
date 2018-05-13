<?php

namespace KRG\IntlBundle\Controller;

use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use KRG\IntlBundle\Translation\TranslationManager;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/intl", name="krg_intl_admin_")
 */
class AdminController extends BaseAdminController
{
    /**
     * @Route("/import", name="import")
     */
    public function importAction(Request $request)
    {

        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        $form = $this->createFormBuilder()
            ->add('file', FileType::class, [
                'label' => 'CSV File'
            ])
            ->add('submit', SubmitType::class, ['label' => 'action.save'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isValid() && $form->isSubmitted()) {
            /** @var UploadedFile $file */
            try {
                $file = $form->get('file')->getData();
                $this->get(TranslationManager::class)->import($file);
            } catch(\Exception $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('KRGIntlBundle:Admin:import.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/export", name="export")
     */
    public function exportAction()
    {
        /** @var \SplFileInfo $fileInfo */
        $fileInfo = $this->get(TranslationManager::class)->export();
        return new BinaryFileResponse($fileInfo, 200, [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => sprintf('attachment; filename="translations_%s.csv"', date('Y-m-d'))
        ]);
    }
}