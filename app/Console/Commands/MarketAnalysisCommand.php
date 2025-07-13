<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarketAnalysisService;
use Carbon\Carbon;

class MarketAnalysisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:analyze {--force : Força a execução da análise mesmo que já exista para o dia.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa a análise diária do mercado de vinis no Discogs.';

    /**
     * O serviço de análise de mercado.
     *
     * @var MarketAnalysisService
     */
    protected $analysisService;

    /**
     * Create a new command instance.
     *
     * @param  MarketAnalysisService  $analysisService
     * @return void
     */
    public function __construct(MarketAnalysisService $analysisService)
    {
        parent::__construct();
        $this->analysisService = $analysisService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🎵 Iniciando análise do mercado Discogs...');

        // A lógica de forçar a execução agora é gerenciada pelo updateOrCreate no serviço.
        // A flag --force não precisa de lógica customizada aqui, pois o serviço sempre
        // irá atualizar ou criar o registro para o dia corrente.

        $this->info('📊 Coletando dados do marketplace...');

        $success = $this->analysisService->performDailyAnalysis();

        if ($success) {
            $this->info('✅ Análise de mercado concluída e salva com sucesso!');
        } else {
            $this->error('❌ Falha ao executar análise. Verifique os logs para mais detalhes.');
            return 1;
        }

        return 0;
    }
}
