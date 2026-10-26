<?php

namespace NYCorp\Finance\Console;

use Illuminate\Console\Command;
use NYCorp\Finance\Integrity\AuditTrail;
use NYCorp\Finance\Integrity\IntegrityVerifier;
use NYCorp\Finance\Models\FinanceAuditLog;

class VerifyIntegrityCommand extends Command
{
    protected $signature = 'finance:verify-integrity
                            {--fix-balances : Recalculate the cached balances that drifted from the transactions}
                            {--json : Print the report as JSON}';

    protected $description = 'Verify signatures, checksums, the hash chain, the audit log and the cached balances of the finance tables';

    public function handle(IntegrityVerifier $verifier): int
    {
        $verifier->run((bool)$this->option('fix-balances'));

        AuditTrail::record(FinanceAuditLog::EVENT_INTEGRITY_VERIFIED, null, null, [
            'valid' => $verifier->isValid(),
            'errors' => count($verifier->errors()),
            'warnings' => count($verifier->warnings()),
        ]);

        if ($this->option('json')) {
            $this->line(json_encode([
                'valid' => $verifier->isValid(),
                'stats' => $verifier->stats(),
                'errors' => $verifier->errors(),
                'warnings' => $verifier->warnings(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $verifier->isValid() ? self::SUCCESS : self::FAILURE;
        }

        foreach ($verifier->stats() as $key => $value) {
            $this->line(sprintf('%-30s %s', $key, is_array($value) ? json_encode($value) : $value));
        }

        foreach ($verifier->warnings() as $warning) {
            $this->warn("[{$warning['type']}] " . ($warning['id'] ? "{$warning['id']}: " : '') . $warning['message']);
        }

        foreach ($verifier->errors() as $error) {
            $this->error("[{$error['type']}] " . ($error['id'] ? "{$error['id']}: " : '') . $error['message']);
        }

        if ($verifier->isValid()) {
            $this->info('Finance integrity verified: no error.');
            return self::SUCCESS;
        }

        $this->error(count($verifier->errors()) . ' integrity error(s) found.');
        return self::FAILURE;
    }
}
