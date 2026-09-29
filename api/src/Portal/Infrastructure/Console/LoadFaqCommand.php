<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Console;

use App\Portal\Application\Help\FaqFile;
use App\Portal\Application\Help\LoadFaq;
use App\Portal\Domain\Help\FaqError;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Carga el fichero de preguntas de la ayuda desde la consola, igual que el
 * botón de la pantalla *Ayuda* (sustituye toda la ayuda):
 *
 *   bin/console app:ayuda:cargar /ruta/a/faq.json
 *
 * Para desarrollo. En producción se carga desde la pantalla: así el fichero
 * no tiene que viajar a Google.
 */
#[AsCommand(name: 'app:ayuda:cargar', description: 'Carga el fichero de preguntas de la ayuda (sustituye la que haya).')]
final readonly class LoadFaqCommand
{
    public function __construct(private LoadFaq $loadFaq)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('El fichero faq.json.')] string $fichero,
    ): int {
        $content = is_file($fichero) ? file_get_contents($fichero) : false;
        if (false === $content) {
            $io->error(sprintf('No se puede leer "%s".', $fichero));

            return Command::FAILURE;
        }

        try {
            $file = json_decode($content, true, 32, \JSON_THROW_ON_ERROR);
            $faq = ($this->loadFaq)(FaqFile::parse($file));
        } catch (\JsonException $e) {
            $io->error(sprintf('"%s" no es JSON: %s', $fichero, $e->getMessage()));

            return Command::FAILURE;
        } catch (FaqError $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('%d preguntas cargadas.', count($faq->getEntries())));
        foreach ($faq->countByApplication() as $application => $count) {
            $io->writeln(sprintf('  %s: %d', $application, $count));
        }

        return Command::SUCCESS;
    }
}
