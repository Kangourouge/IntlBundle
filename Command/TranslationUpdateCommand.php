<?php

namespace KRG\IntlBundle\Command;

use KRG\IntlBundle\Translation\TranslationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
        $defaultDest = sprintf('%s/translations_%s.csv', getcwd(), date('Y-m-d'));

        $this->setName('krg:intl:dump')
                ->addArgument('dest', InputArgument::OPTIONAL, 'Destination CSV file path', $defaultDest);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \SplFileInfo $fileInfo */
        $fileInfo = $this->translationManager->export();

        if (@copy($fileInfo->getRealPath(), $dest=$input->getArgument('dest'))) {
            $output->writeln(sprintf('<info>You can find translations file dump in "%s".</info>', $dest));
        } else {
            $output->writeln(sprintf('<error>Unable to save translations file dump in "%s".</error>', $dest));
        }
    }
}