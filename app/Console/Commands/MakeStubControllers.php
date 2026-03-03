<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Commande artisan : Crée tous les contrôleurs stub manquants.
 * Usage : php artisan macif:stubs
 */
class MakeStubControllers extends Command
{
    protected $signature   = 'macif:stubs';
    protected $description = 'Crée tous les contrôleurs stub MACIF CHICKEN (évite les erreurs route:list)';

    // Définition de tous les stubs à créer : namespace => [classes]
    private array $stubs = [
        'Public' => [
            'StockPublicController' => "
    public function index(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Non implémenté', 'data' => null], 501);
    }

    public function show(int \$id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Non implémenté', 'data' => null], 501);
    }",
            'EleveurPublicController' => "
    public function show(int \$id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Non implémenté', 'data' => null], 501);
    }",
        ],
        'Eleveur' => [
            'ProfileController'      => null,
            'StockController'        => null,
            'CommandeController'     => null,
            'DashboardController'    => null,
            'AvisController'         => null,
            'AbonnementController'   => null,
            'TransactionController'  => null,
        ],
        'Acheteur' => [
            'ProfileController'   => null,
            'CommandeController'  => null,
            'DashboardController' => null,
            'FavoriController'    => null,
        ],
        'Shared' => [
            'CommandeSharedController' => null,
            'NotificationController'   => null,
            'AvisController'           => null,
            'PaiementController'       => null,
        ],
        'Admin' => [
            'DashboardController' => null,
            'UserController'      => null,
            'StockController'     => null,
            'CommandeController'  => null,
            'LitigeController'    => null,
            'FinanceController'   => null,
        ],
    ];

    // Méthode par défaut pour les stubs génériques
    private string $defaultMethod = "
    public function __call(string \$name, array \$args): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Non implémenté', 'data' => null], 501);
    }";

    public function handle(): int
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->stubs as $subfolder => $classes) {
            $dir = app_path("Http/Controllers/{$subfolder}");
            File::ensureDirectoryExists($dir);

            foreach ($classes as $className => $methods) {
                $path = "{$dir}/{$className}.php";

                // Ne pas écraser un fichier déjà implémenté (plus de 5 lignes)
                if (File::exists($path) && count(File::lines($path)) > 10) {
                    $this->line("  <fg=yellow>SKIP</> {$subfolder}/{$className}.php (déjà implémenté)");
                    $skipped++;
                    continue;
                }

                $body    = $methods ?? $this->defaultMethod;
                $content = $this->generateClass($subfolder, $className, $body);

                File::put($path, $content);
                $this->line("  <fg=green>OK</>   {$subfolder}/{$className}.php");
                $created++;
            }
        }

        $this->newLine();
        $this->info("✅ {$created} fichiers créés, {$skipped} ignorés (déjà implémentés).");
        $this->info('Lance maintenant : php artisan route:list');

        return self::SUCCESS;
    }

    private function generateClass(string $subfolder, string $className, string $methods): string
    {
        return <<<PHP
<?php

namespace App\Http\Controllers\\{$subfolder};

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** STUB — sera remplacé au sprint {$subfolder} */
class {$className} extends Controller
{{$methods}
}
PHP;
    }
}