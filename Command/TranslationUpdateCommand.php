<?php

namespace KRG\IntlBundle\Command;

use KRG\IntlBundle\Entity\Manager\TranslationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationUpdateCommand extends Command
{
    /**
     * @var TranslationManager
     */
    private $translationManager;

    /**
     * TranslationUpdateCommand constructor.
     *
     * @param TranslationManager $translationManager
     */
    public function __construct(TranslationManager $translationManager)
    {
        parent::__construct();
        $this->translationManager = $translationManager;
    }

    protected function configure()
    {
        $this->setName('krg:intl:update');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->translationManager->dump();
    }
}