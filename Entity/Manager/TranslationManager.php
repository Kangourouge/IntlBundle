<?php

namespace KRG\IntlBundle\Entity\Manager;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gedmo\Translatable\Translatable;
use KRG\IntlBundle\Entity\TranslationInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;

class TranslationManager
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ClassMetadata
     */
    private $classMetadata;

    /**
     * @var TranslationReaderInterface
     */
    private $reader;

    /**
     * @var ExtractorInterface
     */
    private $extractor;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var string
     */
    private $translationCacheDir;

    /**
     * @var array
     */
    private $locales;

    /**
     * @var string
     */
    private $defaultTransPath;

    /**
     * @var string
     */
    private $defaultViewsPath;

    /**
     * @var array
     */
    private static $header = ['locale', 'object_class', 'field', 'foreign_key', 'content'];

    /**
     * TranslationManager constructor.
     *
     * @param KernelInterface $kernel
     * @param EntityManagerInterface $entityManager
     * @param TranslationReaderInterface $reader
     * @param ExtractorInterface $extractor
     * @param string $defaultLocale
     * @param array $locales
     * @param string $defaultTransPath
     * @param string $defaultViewsPath
     */
    public function __construct(KernelInterface $kernel, EntityManagerInterface $entityManager, TranslationReaderInterface $reader, ExtractorInterface $extractor, string $defaultLocale, array $locales, string $translationCacheDir = null, string $defaultTransPath = null, string $defaultViewsPath = null)
    {
        $this->kernel = $kernel;
        $this->entityManager = $entityManager;
        $this->reader = $reader;
        $this->extractor = $extractor;
        $this->defaultLocale = $defaultLocale;
        $this->locales = $locales;
        $this->translationCacheDir = $translationCacheDir;
        $this->defaultTransPath = $defaultTransPath;
        $this->defaultViewsPath = $defaultViewsPath;

        $this->classMetadata = $this->entityManager->getClassMetadata(TranslationInterface::class);
    }

    /**
     * @param UploadedFile $file
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    public function import(UploadedFile $file)
    {
        assert($file->getClientMimeType() === 'text/csv');

        if (($handle = fopen($file->getPathname(), "r")) !== false) {
            try {

                $data = fgetcsv($handle, 0, ",");

                if ($data !== self::$header) {
                    throw new \InvalidArgumentException('CSV not valid');
                }

                /** @var Connection $conn */
                $conn = $this->entityManager->getConnection();
                $conn->beginTransaction();

                $tableName = $this->classMetadata->getTableName();

                $rows = $this->loadTranslations($conn, $tableName);

                while (($data = fgetcsv($handle, 0, ",")) !== false) {
                    if (count($data) !== 5) {
                        throw new \InvalidArgumentException('CSV not valid');
                    }
                    list($locale, $objectClass, $field, $foreignKey, $content) = $data;
                    $foreignTextKey = $foreignKey;

                    if ($objectClass === '_source') {
                        $foreignKey = sha1($foreignKey);
                    }

                    $this->insertOrUpdate($conn, $rows, $tableName, $locale, $objectClass, $field, $foreignKey, $foreignTextKey, $content);
                }
                $conn->commit();
            } catch (\Exception $exception) {
                $conn->rollBack();
                throw $exception;
            } finally {
                fclose($handle);
            }
        }
    }

    /**
     * @return \SplFileInfo
     */
    public function export()
    {
        $rows = [];

        $tableName = $this->classMetadata->getTableName();
        $conn = $this->entityManager->getConnection();

        $rows = array_merge($rows, $this->loadEntityTranslations($conn, $tableName));
        $rows = array_merge($rows, $this->loadSourceTranslations($conn, $tableName));
        $rows = array_merge($rows, $this->loadTranslations($conn, $tableName));

        $rows = array_values($rows);

        $fileInfo = new \SplFileInfo(tempnam(sys_get_temp_dir(), 'intl_translation_export_'));

        $fd = $fileInfo->openFile('w');
        $fd->fputcsv(self::$header);
        foreach ($rows as $row) {
            $fd->fputcsv($row);
        }

        return $fileInfo;
    }

    public function dump()
    {
        $tableName = $this->classMetadata->getTableName();
        $conn = $this->entityManager->getConnection();

        $rows = $this->loadSourceTranslations($conn, $tableName);

        $rows = array_values($rows);
    }

    /**
     * @return array
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    public function getTranslatableFields()
    {

        $fields = array();

        $annotationReader = new AnnotationReader();

        $classMetadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();

        /** @var ClassMetadata $classMetadata */
        foreach ($classMetadatas as $classMetadata) {
            if ($classMetadata->isMappedSuperclass) {
                continue;
            }
            $reflectionClass = $classMetadata->getReflectionClass();
            if ($reflectionClass->implementsInterface(Translatable::class)) {
                foreach ($reflectionClass->getProperties() as $reflectionProperty) {
                    $annotation = $annotationReader->getPropertyAnnotation($reflectionProperty, \Gedmo\Mapping\Annotation\Translatable::class);
                    if ($annotation) {
                        $fields[] = [
                            'fieldName'  => $reflectionProperty->name,
                            'columnName' => $classMetadata->getColumnName($reflectionProperty->name),
                            'className'  => $classMetadata->getName(),
                            'tableName'  => $classMetadata->getTableName(),
                        ];
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private static function getKey(array $data)
    {
        return sprintf('%s-%s-%s-%s', $data['locale'], sha1($data['object_class']), $data['field'], $data['foreign_key']);
    }

    /**
     * @param Connection $conn
     * @param $tableName
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function loadTranslations(Connection $conn, $tableName)
    {
        $data = $conn->executeQuery(sprintf('SELECT * FROM %s', $tableName));

        $rows = [];
        foreach ($data as $_data) {
            if ($_data['object_class'] === '_source') {
                $_data['foreign_key'] = $_data['foreign_text_key'];
                unset($_data['foreign_text_key']);
            }
            $rows[self::getKey($_data)] = $_data;
        }

        return $rows;
    }

    /**
     * @param Connection $conn
     * @param array $rows
     * @param $tableName
     * @param $locale
     * @param $objectClass
     * @param $field
     * @param $foreignKey
     * @param $content
     *
     * @return int
     */
    private function insertOrUpdate(Connection $conn, array &$rows, $tableName, $locale, $objectClass, $field, $foreignKey, $foreignTextKey, $content)
    {
        $data = [
            'locale'       => $locale,
            'object_class' => $objectClass,
            'field'        => $field,
            'foreign_key'  => $foreignKey,
            'foreign_text_key'  => $foreignTextKey,
            'content'      => $content,
        ];

        $key = self::getKey($data);

        if (!isset($rows[$key])) {
            $rows[$key] = $data;
            if ($conn->insert($tableName, $data)) {
                return true;
            }
            return false;
        }

        unset($data['content']);

        return (bool) $conn->update($tableName, ['content' => $content], $data);
    }

    /**
     * @param Connection $conn
     * @param string $tableName
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function loadEntityTranslations(Connection $conn, string $tableName)
    {
        $query = [];
        foreach ($this->getTranslatableFields() as $field) {
            $query[] = sprintf(
                'SELECT \'%3$s\' AS object_class, \'%1$s\' AS field, id AS foreign_key, %2$s AS content FROM %4$s WHERE %2$s IS NOT NULL',
                $field['fieldName'],
                $field['columnName'],
                addslashes($field['className']),
                $field['tableName']
            );
        }
        $data = $conn->executeQuery(implode(' UNION ', $query))->fetchAll();

        $rows = [];
        foreach ($this->locales as $locale) {
            foreach ($data as $_data) {
                $_data = array_merge(['locale' => $locale], $_data);
                $rows[self::getKey($_data)] = $_data;
            }
        }

        return $rows;
    }

    /**
     * @param Connection $conn
     * @param string $tableName
     *
     * @return array
     */
    private function loadSourceTranslations(Connection $conn, string $tableName)
    {
        // Define Root Paths
        $transPaths = array($this->kernel->getRootDir().'/Resources/translations');
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }
        $viewsPaths = array($this->kernel->getRootDir().'/Resources/views');
        if ($this->defaultViewsPath) {
            $viewsPaths[] = $this->defaultViewsPath;
        }

        foreach ($this->kernel->getBundles() as $bundle) {
            $transPaths[] = $bundle->getPath().'/Resources/translations';
            if ($this->defaultTransPath) {
                $transPaths[] = $this->defaultTransPath.'/'.$bundle->getName();
            }
            $transPaths[] = sprintf('%s/Resources/%s/translations', $this->kernel->getRootDir(), $bundle->getName());
            $viewsPaths[] = $bundle->getPath().'/Resources/views';
            if ($this->defaultViewsPath) {
                $viewsPaths[] = $this->defaultViewsPath.'/bundles/'.$bundle->getName();
            }
            $viewsPaths[] = sprintf('%s/Resources/%s/views', $this->kernel->getRootDir(), $bundle->getName());
        }

        $rows = [];

        foreach ($this->locales as $locale) {
            // Extract used messages
            $extractedCatalogue = $this->extractMessages($locale, $viewsPaths);

            // Load defined messages
            $currentCatalogue = $this->loadCurrentMessages($locale, $transPaths);

            // Merge defined and extracted messages to get all message ids
            $mergeOperation = new MergeOperation($extractedCatalogue, $currentCatalogue);
            $messages = $mergeOperation->getResult()->all();

            foreach ($messages as $domain => $_messages) {
                foreach ($_messages as $foreignKey => $content) {
                    $data = [
                        'locale'       => $locale,
                        'object_class' => '_source',
                        'field'        => $domain,
                        'foreign_key'  => $foreignKey,
                        'content'      => $content,
                    ];
                    $rows[self::getKey($data)] = $data;
                }
            }
        }

        return $rows;
    }

    /**
     * @param string $locale
     * @param array $transPaths
     *
     * @return MessageCatalogue
     */
    private function extractMessages($locale, $transPaths)
    {
        $extractedCatalogue = new MessageCatalogue($locale);
        foreach ($transPaths as $path) {
            if (is_dir($path)) {
                $this->extractor->extract($path, $extractedCatalogue);
            }
        }

        return $extractedCatalogue;
    }

    /**
     * @param string $locale
     * @param array $transPaths
     *
     * @return MessageCatalogue
     */
    private function loadCurrentMessages($locale, $transPaths)
    {
        $currentCatalogue = new MessageCatalogue($locale);
        foreach ($transPaths as $path) {
            if (is_dir($path)) {
                $this->reader->read($path, $currentCatalogue);
            }
        }

        return $currentCatalogue;
    }
}