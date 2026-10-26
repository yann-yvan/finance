<?php

namespace NYCorp\Finance\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Models\FinanceLedger;

trait FinanceLedgerTrait
{
    public function wallets(): HasMany
    {
        return $this->hasMany(FinanceLedger::class, FinanceLedger::OWNER_ID)->where(FinanceLedger::OWNER_TYPE, __CLASS__);
    }

    public function wallet(string $name = "main", ?string $currency = null): FinanceLedger
    {
        // A null currency is stored as the default one: query it as such, or a new ledger is created at each call
        return FinanceLedger::firstOrCreate([
            FinanceLedger::NAME => $name,
            FinanceLedger::CURRENCY => $currency ?? ConfigReader::getDefaultCurrency(),
            FinanceLedger::OWNER_ID => $this->getKey(),
            FinanceLedger::OWNER_TYPE => __CLASS__,
        ]);
    }
}