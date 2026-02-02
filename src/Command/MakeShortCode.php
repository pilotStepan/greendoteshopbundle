<?php

namespace Greendot\EshopBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'make:shortcode', description: 'Creates a new shortcode service class')]
class MakeShortCode extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the shortcode (e.g NewsSection)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        if (!$name) $name = $io->ask('What is the name of the new Shortcode class?', uniqid('ShortCode'));
        $name = ucfirst($name);
        $filePath = "src/Service/ShortCodes/$name.php";
        if (file_exists($filePath)){
            $io->error("File $filePath already exists!");
            return Command::FAILURE;
        }

        $template = $this->getTemplate($name);

        if (!is_dir('src/Service/ShortCodes')) {
            mkdir('src/Service/ShortCodes', 0777, true);
        }
        file_put_contents($filePath, $template);

        $io->success("Shortcode $name created successfully at $filePath");
        $io->note("Don't forget to define the supported fields and logic in replaceableContent()!");

        return Command::SUCCESS;
    }


    private function getTemplate(string $className): string
    {
        $lowerClassName = strtolower($className);

        return <<<PHP
            <?php
            
            namespace App\Service\ShortCodes;
            
            use Greendot\EshopBundle\Service\ShortCodes\ShortCodeBase;
            use Greendot\EshopBundle\Service\ShortCodes\ShortCodeInterface;
            
            class $className extends ShortCodeBase implements ShortCodeInterface
            {
                public function __construct()
                {
                }
            
                /**
                 * @inheritDoc
                 */
                public function regex(): string
                {
                    // Example: /@$lowerClassName\[(\d+)\]@/
                    return '/@placeholder\[(\d+)\]@/';
                }
            
                /**
                 * @inheritDoc
                 */
                public function supportedFields(): array
                {
                    return [
                        // ExampleEntity::class => ['html', 'description']
                    ];
                }
            
                /**
                 * @inheritDoc
                 */
                public function replaceableContent(object \$object, ?array \$data = null): string
                {
                    if (empty(\$data)) return '';
            
                    // \$data[0] contains the first regex match
                    return "";
                }
            }
            PHP;
    }
}