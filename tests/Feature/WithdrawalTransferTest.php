<?php

namespace NYCorp\Finance\Tests\Feature;

use NYCorp\Finance\Http\Payment\DefaultPaymentProvider;
use NYCorp\Finance\Models\FinanceAccount;
use NYCorp\Finance\Models\FinanceAuditLog;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Scope\InvalidWalletScope;
use NYCorp\Finance\Tests\Fixtures\FakeGateway;
use NYCorp\Finance\Tests\TestCase;

class WithdrawalTransferTest extends TestCase
{
    public function test_debit_within_balance(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 100, 'top up');

        $response = $user->debit(DefaultPaymentProvider::getId(), 30, 'usage');

        $this->assertTrue($response->isSuccess(), $response->getMessage());
        $this->assertEquals(70, $user->fresh()->balance);
        $this->assertEquals(-30, (float)FinanceTransaction::debit()->sole()->amount);
    }

    public function test_sub_cent_amounts_keep_valid_signatures(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 1, 'top up');

        foreach ([0.000228, 0.00457, 0.0000049, 1e-5] as $amount) {
            $response = $user->debit(DefaultPaymentProvider::getId(), $amount, 'per message fee');
            if (round($amount, 5) == 0) {
                $this->assertFalse($response->isSuccess());
                continue;
            }
            $this->assertTrue($response->isSuccess(), $response->getMessage());
        }

        foreach (FinanceTransaction::all() as $transaction) {
            $this->assertTrue($transaction->hasValidStartSignature(), (string)$transaction->amount);
            $this->assertTrue($transaction->verifyChecksum(), (string)$transaction->amount);
        }
    }

    public function test_debit_beyond_balance_is_refused_without_leaving_a_transaction(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'top up');

        $response = $user->debit(DefaultPaymentProvider::getId(), 10.5, 'too much');

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('Not enough balance', $response->getMessage());
        $this->assertSame(1, FinanceTransaction::count());
        $this->assertSame(1, FinanceWallet::withoutGlobalScope(InvalidWalletScope::class)->count());
        $this->assertEquals(10, $user->fresh()->balance);
    }

    public function test_pending_debits_reserve_the_balance(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 100, 'top up');

        // Asynchronous payout still waiting for its callback
        $pending = $user->debit(FakeGateway::getId(), 80, 'payout');
        $this->assertTrue($pending->isSuccess(), $pending->getMessage());
        $this->assertSame(FinanceTransaction::STATE_PENDING, FinanceTransaction::debit()->sole()->state);

        // The balance is still 100, but 80 are reserved: a second payout of 80 would spend the same money
        $second = $user->debit(DefaultPaymentProvider::getId(), 80, 'double spend');
        $this->assertFalse($second->isSuccess());

        $allowed = $user->debit(DefaultPaymentProvider::getId(), 20, 'fits');
        $this->assertTrue($allowed->isSuccess(), $allowed->getMessage());
    }

    public function test_old_pending_debits_no_longer_reserve(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 100, 'top up');
        $user->debit(FakeGateway::getId(), 80, 'payout');

        $this->travel(2)->hours();

        $this->assertTrue($user->debit(DefaultPaymentProvider::getId(), 80, 'after ttl')->isSuccess());
    }

    public function test_provider_refusal_fails_the_transaction_instead_of_leaving_it_pending(): void
    {
        $user = $this->user();
        FakeGateway::$acceptStart = false;

        $response = $user->credit(FakeGateway::getId(), 10, 'refused');

        $this->assertFalse($response->isSuccess());
        $transaction = FinanceTransaction::sole();
        $this->assertSame(FinanceTransaction::STATE_FAILED, $transaction->state);
        $this->assertTrue((bool)$transaction->is_locked);
        $this->assertSame('provider_refused', $transaction->end_log['context']['reason']);
    }

    public function test_provider_exception_fails_the_transaction(): void
    {
        $user = $this->user();
        FakeGateway::$throwOnStart = true;

        $response = $user->credit(FakeGateway::getId(), 10, 'down');

        $this->assertFalse($response->isSuccess());
        $this->assertSame(FinanceTransaction::STATE_FAILED, FinanceTransaction::sole()->state);
    }

    public function test_transfer_moves_money_and_links_wallets(): void
    {
        $alice = $this->user('alice');
        $bob = $this->user('bob');
        $alice->credit(DefaultPaymentProvider::getId(), 100, 'top up');

        $response = $alice->transfer($bob, DefaultPaymentProvider::getId(), 40, 'gift');

        $this->assertTrue($response->isSuccess(), $response->getMessage());
        $this->assertEquals(60, $alice->fresh()->balance);
        $this->assertEquals(40, $bob->fresh()->balance);
        $bobWallet = FinanceWallet::where('owner_id', $bob->id)->sole();
        $this->assertTrue($bobWallet->isTransfer());
        $this->assertSame($alice->id, (int)$bobWallet->transferFromWallet->owner_id);
    }

    public function test_transfer_beyond_balance_changes_nothing(): void
    {
        $alice = $this->user('alice');
        $bob = $this->user('bob');
        $alice->credit(DefaultPaymentProvider::getId(), 10, 'top up');

        $response = $alice->transfer($bob, DefaultPaymentProvider::getId(), 40, 'gift');

        $this->assertFalse($response->isSuccess());
        $this->assertEquals(10, $alice->fresh()->balance);
        $this->assertEquals(0, $bob->fresh()->balance);
        $this->assertSame(1, FinanceTransaction::count());
    }

    public function test_threshold_change_is_audited(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'top up');

        $user->setThreshold(-50);

        $log = FinanceAuditLog::where('event', FinanceAuditLog::EVENT_ACCOUNT_THRESHOLD_CHANGED)->sole();
        $this->assertSame('-50.00000', $log->new_values['threshold']);
        $this->assertTrue($user->fresh()->debit(DefaultPaymentProvider::getId(), 40, 'overdraft')->isSuccess());
    }

    public function test_balance_drift_is_repaired_by_verification_and_audited(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'top up');
        FinanceAccount::query()->update(['credibility' => 999]);

        $this->assertEquals(10, $user->balanceChecksum(true, 'USD'));

        $log = FinanceAuditLog::where('event', FinanceAuditLog::EVENT_ACCOUNT_RECALCULATED)->latest('id')->first();
        $this->assertSame('999.00000', $log->old_values['credibility']);
        $this->assertSame('10.00000', $log->new_values['credibility']);
    }
}
