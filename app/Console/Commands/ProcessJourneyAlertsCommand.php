<?php

namespace App\Console\Commands;

use App\Actions\Journeys\ProcessJourneyAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('journeys:process-alerts')]
#[Description('Detecta inactividad, escala alertas sin respuesta y cierra trayectos abandonados')]
class ProcessJourneyAlertsCommand extends Command
{
    public function handle(ProcessJourneyAlerts $alerts): int
    {
        $closed = $alerts->closeAbandoned();
        $raised = $alerts->raisePreliminaryAlerts();
        $escalated = $alerts->escalateDueAlerts();

        $this->line("Trayectos cerrados: {$closed}. Alertas preliminares: {$raised}. Alertas escaladas: {$escalated}.");

        return self::SUCCESS;
    }
}
