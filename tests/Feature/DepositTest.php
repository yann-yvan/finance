<?php

namespace NYCorp\Finance\Tests\Feature;

use Illuminate\Support\Facades\Event;
use NYCorp\Finance\Events\FinanceTransactionSuccessEvent;
use NYCorp\Finance\Http\Payment\DefaultPaymentProvider;
use NYCorp\Finance\Models\FinanceAccount;
use NYCorp\Finance\Models\FinanceAuditLog;
use NYCorp\Finance\Models\FinanceChainEntry;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Tests\TestCase;

class DepositTest extends TestCase
{
    public function test_internal_deposit_completes_and_updates_the_balance(): void
    {
        Event::fake([FinanceTransactionSuccessEvent::class]);
        $user = $this->user();

        $response = $user->credit(DefaultPaymentProvider::getId(), 100, 'top up');

        $this->assertTrue($response->isSuccess(), $response->getMessage());
        $transaction = FinanceTransaction::sole();
        $this->assertSame(FinanceTransaction::STATE_COMPLETED, $transaction->state);
        $this->assertTrue((bool)$transaction->is_locked);
        $this->assertSame(3, (int)$transaction->signature_version);
        $this->assertStringStartsWith('v3:k1:', $transaction->start_signature);
        $this->assertStringStartsWith('v3:k1:', $transaction->checksum);
        $this->assertTrue($transaction->verifyChecksum());
        $this->assertTrue(FinanceWallet::sole()->verifyChecksum());
        $this->assertEquals(100, $user->fresh()->balance);
        Event::assertDispatched(FinanceTransactionSuccessEvent::class, fn($event) => $event->model->is($user) && $event->financeTransaction->id === $transaction->id);
    }

    public function test_successive_deposits_increment_the_cached_balance(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 100, 'first');
        $this->assertEquals(100, $user->balance);

        $user->credit(DefaultPaymentProvider::getId(), 50.5, 'second');
        $user->credit(DefaultPaymentProvider::getId(), 50.5, 'second'); // identical: must be a new transaction

        $this->assertSame(3, FinanceTransaction::count());
        $account = FinanceAccount::sole();
        $this->assertEquals(201, (float)$account->credibility);
        $this->assertEquals(201, $user->balanceChecksum(true, 'USD'));
    }

    public function test_finalized_transactions_are_sealed_in_the_chain_and_audited(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'a');
        $user->credit(DefaultPaymentProvider::getId(), 20, 'b');

        $entries = FinanceChainEntry::orderBy('owner_seq')->get();
        $this->assertCount(2, $entries);
        $this->assertSame([1, 2], $entries->pluck('owner_seq')->all());
        $this->assertNull($entries[0]->previous_hash);
        $this->assertSame($entries[0]->hash, $entries[1]->previous_hash);
        $this->assertSame((string)$user->id, $entries[0]->owner_id);
        $this->assertTrue($entries[1]->verifyHash());

        $finalized = FinanceAuditLog::where('event', FinanceAuditLog::EVENT_TRANSACTION_FINALIZED)->get();
        $this->assertCount(2, $finalized);
        $this->assertSame(FinanceTransaction::STATE_PENDING, $finalized[0]->old_values['state']);
        $this->assertSame(FinanceTransaction::STATE_COMPLETED, $finalized[0]->new_values['state']);
        $this->assertTrue($finalized[0]->verifyHash());
    }

    public function test_zero_amount_is_refused_unless_explicitly_allowed(): void
    {
        $user = $this->user();

        $refused = $user->credit(DefaultPaymentProvider::getId(), 0, 'free plan');
        $this->assertFalse($refused->isSuccess());
        $this->assertSame(0, FinanceTransaction::count());

        $granted = $user->credit(DefaultPaymentProvider::getId(), 0, 'free plan', allowZeroAmount: true);
        $this->assertTrue($granted->isSuccess(), $granted->getMessage());
        $transaction = FinanceTransaction::sole();
        $this->assertSame(FinanceTransaction::STATE_COMPLETED, $transaction->state);
        $this->assertEquals(0, (float)$transaction->amount);
        $this->assertSame(1, FinanceChainEntry::count());
    }

    public function test_unknown_exchange_rate_is_a_clean_validation_error(): void
    {
        $user = $this->user();

        $response = $user->credit(DefaultPaymentProvider::getId(), 10, 'fx', 'USD', providerCurrency: 'XAF');

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('No exchange rate available from USD to XAF', $response->getMessage());
        $this->assertSame(0, FinanceTransaction::count());
    }

    public function test_deposit_on_unsaved_model_is_refused(): void
    {
        $response = (new \NYCorp\Finance\Tests\Fixtures\User(['name' => 'ghost']))->credit(DefaultPaymentProvider::getId(), 10, 'x');
        $this->assertFalse($response->isSuccess());
        $this->assertSame(0, FinanceTransaction::count());
    }
}
