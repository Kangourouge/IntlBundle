<?php

namespace KRG\IntlBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use KRG\IntlBundle\Entity\TranslationInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

class TranslationLoader implements LoaderInterface, CacheWarmerInterface, CacheClearerInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var array
     */
    private $locales;

    /**
     * @var string
     */
    private $translationCacheDir;

    /**
     * TranslationLoader constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param array $locales
     * @param string $translationCacheDir
     */
    public function __construct(EntityManagerInterface $entityManager, array $locales, string $translationCacheDir)
    {
        $this->entityManager = $entityManager;
        $this->locales = $locales;
        $this->translationCacheDir = $translationCacheDir;
    }

    public function clear($cacheDir)
    {
        $this->warmUp($cacheDir);
    }

    public function warmUp($cacheDir)
    {
        try {
            $repository = $this->entityManager->getRepository(TranslationInterface::class);

            $dql = $this->entityManager->createQuery(
                sprintf(
                    'SELECT DISTINCT t.locale, t.field FROM %s t WHERE t.objectClass=:objectClass',
                    TranslationInterface::class
                )
            );

            $rows = $dql->execute(['objectClass' => '_source']);

            if (file_exists($this->translationCacheDir)) {
                foreach ($rows as $row) {
                    $filename = sprintf('%s/%s.%s.db', $this->translationCacheDir, $row['field'], $row['locale']);
                    $fd = fopen($filename, 'w');
                    fwrite($fd, '-- empty line --'.PHP_EOL);
                    fclose($fd);
                }
            }
        } catch (\Exception $exception) {
            // TODO handle exception
        }
    }

    public function isOptional()
    {
        return false;
    }

    public function load($resource, $locale, $domain = 'messages')
    {
        try {
            $repository = $this->entityManager->getRepository(TranslationInterface::class);

            $dql = $this->entityManager->createQuery(
                sprintf(
                    'SELECT t.foreignTextKey, t.content
                FROM %s t
                WHERE t.objectClass=:objectClass
                AND t.locale=:locale
                AND t.field=:field',
                    TranslationInterface::class
                )
            );

            $rows = $dql->execute(['locale' => $locale, 'objectClass' => '_source', 'field' => $domain]);
            $messages = array_column($rows, 'content', 'foreignTextKey');

            $messageCatalogue = new MessageCatalogue($locale);
            $messageCatalogue->add($messages, $domain);

            return $messageCatalogue;
        } catch (\Exception $exception) {
            // TODO handle exception
        }
    }
}