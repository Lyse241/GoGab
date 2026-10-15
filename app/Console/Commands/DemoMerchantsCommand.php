<?php

namespace App\Console\Commands;

use App\Services\DemoMerchantAccounts;
use Illuminate\Console\Command;

class DemoMerchantsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'gogab:demo-merchants
        {--include-owned : Réaligne aussi les commerces qui ont déjà un propriétaire (e-mail et mot de passe)}';

    /**
     * @var string
     */
    protected $description = 'Crée un compte entreprise par commerce (nomducommerce@gogab.com, mot de passe « password ») et affiche la liste';

    public function handle(DemoMerchantAccounts $accounts): int
    {
        $rows = $accounts->ensure((bool) $this->option('include-owned'));

        $this->table(
            ['Commerce', 'E-mail', 'Mot de passe', 'Nouveau'],
            array_map(fn (array $row) => [$row['store'], $row['email'], $row['password'], $row['created'] ? 'oui' : 'non'], $rows),
        );
        $this->info(count($rows).' commerce(s) : connexion avec l’e-mail ci-dessus.');

        return self::SUCCESS;
    }
}
