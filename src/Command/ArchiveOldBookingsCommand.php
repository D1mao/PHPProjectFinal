<?php

namespace App\Command;

use App\Service\BookingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bookings:archive-old',
    description: 'Заархивировать завершённые брони'
)]
class ArchiveOldBookingsCommand extends Command
{
    public function __construct(
        private BookingService $bookingService,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Заархивировать брони, завершившиеся X дней назад',
                1,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $thresholdDate = new \DateTime("-$days days");

        $io->title('Команда архивации старых броней');
        $io->text([
            "Ищем брони, завершившиеся до: " . $thresholdDate->format('Y-m-d H:i:s'),
            "Максимум дней назад: $days"
        ]);

        try {
            $archivedCount = $this->bookingService->archiveBookingsOlderThan($thresholdDate);
                
            if ($archivedCount > 0) {
                $io->success("Успешно заархивировано $archivedCount старых броней");
            } else {
                $io->info('Нет броней для архивации');
            }
                
            $this->logger->info('Команда архивации броней выполнена', [
                'archived_count' => $archivedCount,
                'days' => $days,
                'threshold' => $thresholdDate->format('Y-m-d H:i:s')
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка при архивации броней: ' . $e->getMessage());
            $this->logger->error('Команда архивации броней завершилась ошибкой', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}