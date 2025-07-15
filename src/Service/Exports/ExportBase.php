<?php

namespace Greendot\EshopBundle\Service\Exports;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Export;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ExportRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use function Zenstruck\Foundry\object;

abstract class ExportBase
{
    //with defaults
    protected string $locale = 'cs';
    protected Currency $currency;


    public function __construct(
        protected readonly string                 $exportType,
        protected readonly string                 $fileName,
        protected readonly string                 $contentXmlElement,
        protected readonly string                 $directory,
        protected readonly Filesystem             $filesystem,
        protected readonly ExportRepository       $exportRepository,
        protected readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface      $messageBus,
        CurrencyRepository                        $currencyRepository
    )
    {
        $this->currency = $currencyRepository->findOneBy(['isDefault' => 1]);
    }

    abstract function generateItem(int $objectId): ?string;

    abstract function generateHead(): ?string;

    public function createMessages(array $objectIds, string $locale = 'cs', ?Currency $currency = null): void
    {
        $export = $this->startExport();
        foreach ($objectIds as $objectId) {
            $message = new \Greendot\EshopBundle\Message\Export($objectId,get_class($this),$export->getId(), $locale, $currency?->getId());
            $this->messageBus->dispatch($message);
        }
    }
    protected function setUp(
        ?string   $locale = null,
        ?Currency $currency = null,
    ): self
    {
        if ($locale) $this->setLocale($locale);
        if ($currency) $this->setCurrency($currency);
        return $this;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function setCurrency(Currency $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    protected function getExportFileName(bool $absolute = false, ?string $prefix = null): string
    {
        $path = "";
        if ($absolute) {
            $path = $this->directory;
        }
        if ($prefix) {
            $path .= $prefix;
        }
        return $path . $this->locale . "_" . $this->fileName;
    }

    final public function startExport(): Export
    {
        $content = $this->getSplitHead()['start'];
        $relativeFilePath = $this->getExportFileName(false, 'temp_');
        $absoluteFilePath = $this->getExportFileName(true, 'temp_');
        if ($this->filesystem->exists($absoluteFilePath)) {
            throw new \Exception("Tried to create new export, but one is already in progress.");
        }
        $export = new Export();
        $export->setDate(new \DateTime("now"));
        $export->setType($this->exportType);
        $export->setFilename($relativeFilePath);
        $this->entityManager->persist($export);
        $this->entityManager->flush();
        $this->filesystem->dumpFile($absoluteFilePath, $content);
        return $export;
    }

    final public function endExport(): void
    {
        $content = $this->getSplitHead()['end'];
        $absoluteFilePath = $this->getExportFileName(true, 'temp_');
        if (!$this->filesystem->exists($absoluteFilePath)) {
            throw new \Exception("File '" . $absoluteFilePath . "' does not exist.");
        }
        $this->filesystem->appendToFile($absoluteFilePath, $content);
        $this->tempToFinal();
    }

    final public function appendItem(int $objectId): void
    {
        $tempPath = $this->getExportFileName(true, 'temp_');
        if (!$this->filesystem->exists($tempPath)) {
            throw new \Exception("File '" . $this->getExportFileName(true, 'temp_') . "' does not exist. Could not append item.");
        }
        $itemXml = $this->generateItem($objectId);
        $this->filesystem->appendToFile($tempPath, $itemXml);
    }

    private function tempToFinal(): void
    {
        $this->entityManager->clear();
        $tempRelativeName = $this->getExportFileName(false, 'temp_'); //temp name
        $finalRelativeName = $this->getExportFileName(false); //final state name
        $renamedRelativeName = $this->getExportFileName(false, time() . '_'); //old file name

        /**
         * check if there even is any temp file it can rename
         */
        if (!$this->filesystem->exists($this->directory . $tempRelativeName)) {
            throw new \Exception("File '" . $this->directory . $tempRelativeName . "' does not exist. Could not changed it to final");
        }

        /**
         * checks for old file with 'final state', if there is one, ite renames it (as a file and in DB)
         */
        if ($this->filesystem->exists($this->directory . $finalRelativeName)) {
            $oldFinalExport = $this->exportRepository->findOneBy(["filename" => $finalRelativeName, "type" => $this->exportType]);
            $this->filesystem->rename($this->directory . $finalRelativeName, $this->directory . $renamedRelativeName);
            if ($oldFinalExport) {
                $oldFinalExport->setFilename($renamedRelativeName);
                $this->entityManager->persist($oldFinalExport);
            }
        }


        /**
         * renames temp file to 'final state' and creates Export record of that file
         */
        $this->filesystem->rename($this->directory . $tempRelativeName, $this->directory . $finalRelativeName);
        $export = $this->exportRepository->findOneBy(['filename' => $tempRelativeName, 'type' => $this->exportType]);
        if (!$export){
            $export = new Export();
            $export->setType($this->exportType);
        }
        $export->setDate(new \DateTime("now"));
        $export->setFilename($finalRelativeName);
        $this->entityManager->persist($export);
        $this->entityManager->flush();
    }

    private function getSplitHead(): array
    {
        $xml = trim($this->generateHead());
        // Find the position of the opening and closing tags of the specified element
        $end_tag = '</' . $this->contentXmlElement . '>';

        $end_tag_position = strpos($xml, $end_tag);
        $end = substr($xml, $end_tag_position);
        $start = substr($xml, 0, $end_tag_position);

        // Return an array containing starting and closing elements
        return array("start" => $start, "end" => $end, 'all' => $xml);
    }

    final protected function removeXMLDeclaration(string $xml): string
    {
        return trim(preg_replace('/<\?xml.*\?>/', '', $xml));
    }

    final protected function addLineBreakBetweenTags(string $xml): string
    {
        // Use str_replace to replace '><' with '>\n<'
        $outputString = str_replace('><', ">\n<", $xml);
        return $outputString;
    }
}