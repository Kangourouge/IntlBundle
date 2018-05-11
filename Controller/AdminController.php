<?php

namespace KRG\IntlBundle\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EMC\FileinputBundle\Form\Type\FileinputType;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;
use Gedmo\Translatable\Translatable;
use KRG\IntlBundle\Entity\Manager\TranslationManager;
use Symfony\Bundle\FrameworkBundle\Command\TranslationDebugCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\MessageCatalogue;

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
    public function downloadAction()
    {
        $fileInfo = $this->get(TranslationManager::class)->export();
        return new BinaryFileResponse($fileInfo, 200, [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => sprintf('attachment; filename="translations_%s.csv"', date('Y-m-d'))
        ]);
    }
}