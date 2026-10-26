<?php

namespace NYCorp\Finance\Tests\Feature;

use Illuminate\Support\Facades\DB;
use NYCorp\Finance\Http\Payment\DefaultPaymentProvider;
use NYCorp\Finance\Integrity\IntegrityVerifier;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceTransactionTag;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Models\TransactionDisplay;
use NYCorp\Finance\Tests\TestCase;

enum PlanFixture: string
{
    case Pro = 'pro';
}

class DisplayAndTagsTest extends TestCase
{
    private function display(): TransactionDisplay
    {
        return TransactionDisplay::make('subscription.payment')
            ->with('plan', 'Pro')
            ->withDate('from', new \DateTimeImmutable('2026-11-01 08:30:00'));
    }

    public function test_display_is_stored_as_key_and_params_and_translated_by_the_host(): void
    {
        $this->user()->credit(DefaultPaymentProvider::getId(), 10, 'internal: plan Pro #42', display: $this->display());

        $transaction = FinanceTransaction::sole();
        $this->assertInstanceOf(TransactionDisplay::class, $transaction->display);
        $this->assertTrue($this->display()->equals($transaction->display));
        $this->assertSame(['key' => 'subscription.payment', 'params' => ['plan' => 'Pro', 'from' => '2026-11-01']], json_decode(DB::table('finance_transactions')->value('display'), true));
        $this->assertSame('subscription.payment', $transaction->displayKey());
        $this->assertSame(['plan' => 'Pro', 'from' => '2026-11-01'], $transaction->displayParams());
        $this->assertSame('internal: plan Pro #42', $transaction->description);

        // No translation in the host: the technical description is the fallback
        $this->assertSame('internal: plan Pro #42', $transaction->describe());

        app('translator')->addLines(['subscription.payment' => 'Abonnement :plan à partir du :from'], 'fr');
        $this->assertSame('Abonnement Pro à partir du 2026-11-01', $transaction->describe('fr'));
    }

    public function test_display_is_part_of_the_v3_signature(): void
    {
        $this->user()->credit(DefaultPaymentProvider::getId(), 10, 'x', display: $this->display());
        $this->dropTrigger();
        DB::table('finance_transactions')->update(['display' => json_encode(['key' => 'refund', 'params' => []])]);

        $transaction = FinanceTransaction::sole();
        $this->assertFalse($transaction->hasValidStartSignature());
        $this->assertFalse((new IntegrityVerifier())->run()->isValid());
    }

    public function test_display_builder_is_typed_and_immutable(): void
    {
        $base = TransactionDisplay::make('plan.granted');
        $withPlan = $base->with('plan', PlanFixture::Pro)->with('free', true)->with('at', new \DateTimeImmutable('2026-11-01T10:00:00+01:00'));

        $this->assertSame([], $base->params());
        $this->assertSame(['plan' => 'pro', 'free' => true, 'at' => '2026-11-01T10:00:00+01:00'], $withPlan->params());
        $this->assertSame('pro', $withPlan->param('plan'));
        $this->assertTrue(TransactionDisplay::fromArray($withPlan->toArray())->equals($withPlan));
        $this->assertNull(TransactionDisplay::fromArray(null));
    }

    public function test_invalid_display_fails_in_the_calling_code(): void
    {
        $this->assertThrows(fn() => TransactionDisplay::make('  '), \InvalidArgumentException::class);
        $this->assertThrows(fn() => TransactionDisplay::make(str_repeat('k', 192)), \InvalidArgumentException::class);
        $this->assertThrows(fn() => TransactionDisplay::make('k', ['positional']), \InvalidArgumentException::class);
        $this->assertThrows(fn() => TransactionDisplay::make('k', ['nested' => ['no']]), \TypeError::class);
        $this->assertThrows(fn() => FinanceTransaction::make()->forceFill(['display' => ['key' => 'raw array']]), \InvalidArgumentException::class);
    }

    public function test_tags_are_set_at_credit_time_and_added_later(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'a', tags: ['merchant:eneo', 'campaign:CMP-12', ' campaign:CMP-12 ']);
        $user->debit(DefaultPaymentProvider::getId(), 3, 'b', tags: ['campaign:CMP-12']);
        $user->credit(DefaultPaymentProvider::getId(), 5, 'c');

        $a = FinanceTransaction::where('description', 'a')->sole();
        $this->assertSame(['campaign:CMP-12', 'merchant:eneo'], $a->tagList());

        // Added on a finalized, locked transaction: tags are not signed
        FinanceTransaction::where('description', 'c')->sole()->tag('invoice:FAC-001', ['merchant:eneo']);
        FinanceTransaction::where('description', 'c')->sole()->tag('invoice:FAC-001'); // no duplicate
        $this->assertSame(5, FinanceTransactionTag::count());

        $this->assertEqualsCanonicalizing(['a', 'b'], FinanceTransaction::tagged('campaign:CMP-12')->pluck('description')->all());
        $this->assertSame(['a'], FinanceTransaction::tagged('campaign:CMP-12', 'merchant:eneo')->pluck('description')->all());
        $this->assertSame(['a'], FinanceTransaction::tagged(['campaign:CMP-12', 'merchant:eneo'])->pluck('description')->all());
        $this->assertEqualsCanonicalizing(['a', 'c'], FinanceTransaction::taggedAny('merchant:eneo', 'nope')->pluck('description')->all());
        $this->assertSame([], FinanceTransaction::tagged('merchant:eneo', 'nope')->pluck('description')->all());

        // Usable from the wallets, without writing a join
        $this->assertSame(2, FinanceWallet::whereHas('transaction', fn($q) => $q->tagged('campaign:CMP-12'))->count());
        $this->assertTrue((new IntegrityVerifier())->run()->isValid());
    }

    public function test_transfer_carries_display_and_tags_on_both_sides(): void
    {
        $alice = $this->user('alice');
        $alice->credit(DefaultPaymentProvider::getId(), 50, 'top up');

        $alice->transfer($this->user('bob'), DefaultPaymentProvider::getId(), 20, 'gift', display: TransactionDisplay::make('transfer.gift'), tags: ['gift']);

        $transfers = FinanceTransaction::tagged('gift')->get();
        $this->assertCount(2, $transfers);
        $this->assertSame(['transfer.gift'], $transfers->map->displayKey()->unique()->values()->all());
    }

    public function test_untag_and_invalid_tags(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'a', tags: ['x', 'y']);
        FinanceTransaction::sole()->untag('x');
        $this->assertSame(['y'], FinanceTransaction::sole()->tagList());

        $this->assertFalse($user->credit(DefaultPaymentProvider::getId(), 10, 'b', tags: [str_repeat('t', 192)])->isSuccess());
        $this->assertSame(1, FinanceTransaction::count());
    }

    private function dropTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS before_finance_transaction_update');
    }
}
