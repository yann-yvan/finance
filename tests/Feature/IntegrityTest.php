<?php

namespace NYCorp\Finance\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use NYCorp\Finance\Exceptions\CompromisedTransactionException;
use NYCorp\Finance\Http\Controllers\FinanceTransactionController;
use NYCorp\Finance\Http\Payment\DefaultPaymentProvider;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Integrity\FinanceSchema;
use NYCorp\Finance\Integrity\IntegrityVerifier;
use NYCorp\Finance\Models\FinanceAuditLog;
use NYCorp\Finance\Models\FinanceChainEntry;
use NYCorp\Finance\Models\FinanceLedger;
use NYCorp\Finance\Models\FinanceProvider;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Models\GatewayPayload;
use NYCorp\Finance\Scope\InvalidWalletScope;
use NYCorp\Finance\Tests\Fixtures\FakeGateway;
use NYCorp\Finance\Tests\TestCase;

class IntegrityTest extends TestCase
{
    private function dropTrigger(string $name): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS $name");
    }

    public function test_locked_transactions_and_append_only_tables_resist_updates(): void
    {
        $this->user()->credit(DefaultPaymentProvider::getId(), 10, 'top up');

        $this->assertThrows(fn() => DB::table('finance_transactions')->update(['amount' => 1000]));
        $this->assertThrows(fn() => DB::table('finance_wallets')->update(['owner_id' => 99]));
        $this->assertThrows(fn() => DB::table('finance_chain_entries')->delete());
        $this->assertThrows(fn() => DB::table('finance_audit_logs')->update(['event' => 'x']));
    }

    public function test_tampered_pending_transaction_cannot_be_finalized(): void
    {
        $this->user()->credit(FakeGateway::getId(), 10, 'pending');
        $transaction = FinanceTransaction::sole();
        DB::table('finance_transactions')->update(['amount' => 1000]);

        $this->expectException(CompromisedTransactionException::class);
        $transaction = $transaction->fresh();
        $transaction->external_id = 'x';
        FinanceTransactionController::close($transaction, FinanceTransaction::STATE_COMPLETED);
    }

    public function test_v3_signature_depends_on_the_key(): void
    {
        $this->user()->credit(DefaultPaymentProvider::getId(), 10, 'top up');
        $transaction = FinanceTransaction::sole();
        $this->assertTrue($transaction->verifyChecksum());

        // An attacker without the key recomputes the hashes: they do not verify
        config()->set('finance.signature.key', 'base64:' . base64_encode(random_bytes(32)));
        $this->assertFalse($transaction->hasValidStartSignature());
        $this->assertFalse($transaction->verifyChecksum());
    }

    public function test_key_rotation_keeps_old_signatures_valid(): void
    {
        $this->user()->credit(DefaultPaymentProvider::getId(), 10, 'signed with k1');

        config()->set('finance.signature.key_id', 'k2');
        config()->set('finance.signature.key', 'base64:' . base64_encode(random_bytes(32)));
        config()->set('finance.signature.previous_keys', 'k1:' . self::KEY);

        $this->user('bob')->credit(DefaultPaymentProvider::getId(), 5, 'signed with k2');

        $transactions = FinanceTransaction::orderBy('amount')->get();
        $this->assertStringStartsWith('v3:k2:', $transactions[0]->checksum);
        $this->assertStringStartsWith('v3:k1:', $transactions[1]->checksum);
        $this->assertTrue((new IntegrityVerifier())->run()->isValid());

        config()->set('finance.signature.previous_keys', '');
        $this->assertFalse((new IntegrityVerifier())->run()->isValid());
    }

    public function test_legacy_v1_and_v2_rows_still_verify(): void
    {
        $user = $this->user();
        config()->set('finance.signature.key', null);

        $user->credit(DefaultPaymentProvider::getId(), 10, 'legacy v2');
        $v2 = FinanceTransaction::sole();
        $this->assertSame(2, (int)$v2->signature_version);
        $this->assertSame(64, strlen($v2->checksum));

        // v1 row as written by the first versions of the library
        $provider = PaymentProviderGateway::load(DefaultPaymentProvider::getId())->getFinanceProvider();
        $id = Str::uuid()->toString();
        DB::table('finance_transactions')->insert([
            'id' => $id, 'amount' => 3, 'currency' => 'USD', 'start_log' => '{}', 'description' => 'legacy v1',
            'start_signature' => hash('sha256', 'id|amount|start_log|state|currency|description|finance_provider_id'),
            'state' => 'PENDING', 'signature_version' => 1, 'finance_provider_id' => $provider->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Wallet hashed before its id existed, as the legacy code did
        DB::table('finance_wallets')->insert([
            'id' => $walletId = Str::uuid()->toString(), 'owner_id' => $user->id, 'owner_type' => $user::class,
            'finance_transaction_id' => $id, 'created_at' => now(), 'updated_at' => now(),
            'checksum' => hash('sha256', implode('|', ['', $user->id, $user::class, null, $id])),
        ]);
        $v1 = FinanceTransaction::find($id);
        $v1->external_id = 'legacy';
        FinanceTransactionController::close($v1, FinanceTransaction::STATE_COMPLETED);

        config()->set('finance.signature.key', self::KEY);
        $this->assertTrue($v1->fresh()->verifyChecksum());
        $this->assertTrue($v2->fresh()->verifyChecksum());
        $this->assertTrue(FinanceWallet::withoutGlobalScope(InvalidWalletScope::class)->find($walletId)->verifyChecksum());
        $this->assertEquals(13, $user->balanceChecksum(true, 'USD'));

        $verifier = (new IntegrityVerifier())->run();
        $this->assertTrue($verifier->isValid(), json_encode($verifier->errors()));
        $this->assertEquals(['v1' => 1, 'v2' => 1], $verifier->stats()['transactions_by_signature']);
        $this->assertNotEmpty(array_filter($verifier->warnings(), fn($w) => str_contains($w['message'], 'v1')));
    }

    public function test_verify_command_passes_on_clean_data(): void
    {
        $alice = $this->user('alice');
        $alice->credit(DefaultPaymentProvider::getId(), 100, 'top up');
        $alice->transfer($this->user('bob'), DefaultPaymentProvider::getId(), 30, 'gift');
        $this->partner()->wallet('marketplace')->credit(DefaultPaymentProvider::getId(), 5, 'sale');

        $this->artisan('finance:verify-integrity')->expectsOutputToContain('no error')->assertSuccessful();
        $this->assertSame(1, FinanceAuditLog::where('event', FinanceAuditLog::EVENT_INTEGRITY_VERIFIED)->count());
    }

    public function test_verify_command_detects_a_rewritten_amount(): void
    {
        $this->user()->credit(DefaultPaymentProvider::getId(), 10, 'top up');
        $this->dropTrigger('before_finance_transaction_update');
        DB::table('finance_transactions')->update(['amount' => 1000]);

        $this->artisan('finance:verify-integrity')->expectsOutputToContain('Start signature does not match')->assertFailed();
    }

    public function test_verify_command_detects_a_deleted_chain_entry_and_a_rewritten_entry(): void
    {
        $user = $this->user();
        foreach ([10, 20, 30] as $amount) {
            $user->credit(DefaultPaymentProvider::getId(), $amount, "credit $amount");
        }
        $this->dropTrigger('prevent_delete_finance_chain_entries');
        $this->dropTrigger('prevent_update_finance_chain_entries');
        DB::table('finance_chain_entries')->where('owner_seq', 2)->delete();
        DB::table('finance_chain_entries')->where('owner_seq', 3)->update(['amount' => 1]);

        $verifier = (new IntegrityVerifier())->run();
        $messages = implode("\n", array_column($verifier->errors(), 'message'));
        $this->assertStringContainsString('expected position 2', $messages);
        $this->assertStringContainsString('link to the previous entry is broken', $messages);
        $this->assertStringContainsString('Chain entry hash does not match', $messages);
        $this->assertStringContainsString('missing from the hash chain', $messages);
    }

    public function test_verify_command_detects_wallet_ledger_audit_and_balance_tampering(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'top up');
        $ledger = $this->partner()->wallet('marketplace');

        $this->dropTrigger('prevent_update_finance_wallets');
        $this->dropTrigger('prevent_update_finance_audit_logs');
        DB::table('finance_wallets')->update(['owner_id' => 42]);
        DB::table('finance_ledgers')->update(['name' => 'stolen']);
        DB::table('finance_audit_logs')->update(['actor_id' => 'someone-else']);
        DB::table('finance_accounts')->update(['credibility' => 999]);

        $verifier = (new IntegrityVerifier())->run();
        $types = array_count_values(array_column($verifier->errors(), 'type'));
        $this->assertArrayHasKey('wallet', $types);
        $this->assertArrayHasKey('ledger', $types);
        $this->assertArrayHasKey('audit', $types);
        $this->assertArrayHasKey('account', $types);
    }

    public function test_fix_balances_recalculates_drifted_accounts(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'top up');
        DB::table('finance_accounts')->update(['credibility' => 999]);

        $this->artisan('finance:verify-integrity --fix-balances')->assertSuccessful();
        $this->assertEquals(10, (float)DB::table('finance_accounts')->value('credibility'));
    }

    public function test_seal_history_chains_legacy_transactions(): void
    {
        $user = $this->user();
        $user->credit(DefaultPaymentProvider::getId(), 10, 'a');
        $user->credit(DefaultPaymentProvider::getId(), 20, 'b');
        // History finalized before the chain existed
        $this->dropTrigger('prevent_delete_finance_chain_entries');
        DB::table('finance_chain_entries')->delete();

        $before = (new IntegrityVerifier())->run();
        $this->assertTrue($before->isValid());
        $this->assertSame(2, $before->stats()['unsealed_legacy_transactions']);

        $this->artisan('finance:seal-history')->expectsOutputToContain('2 transaction(s) sealed')->assertSuccessful();

        $after = (new IntegrityVerifier())->run();
        $this->assertTrue($after->isValid(), json_encode($after->errors()));
        $this->assertSame(0, $after->stats()['unsealed_legacy_transactions']);
        $this->assertSame(2, FinanceChainEntry::count());
    }

    public function test_expire_pending_cancels_old_pending_transactions(): void
    {
        $user = $this->user();
        $user->credit(FakeGateway::getId(), 10, 'old');
        $this->travel(8)->days();
        $user->credit(FakeGateway::getId(), 10, 'recent');

        $this->artisan('finance:expire-pending --dry-run')->expectsOutputToContain('1 pending')->assertSuccessful();
        $this->artisan('finance:expire-pending')->expectsOutputToContain('1 pending transaction(s) canceled')->assertSuccessful();

        $this->assertSame(['CANCELED', 'PENDING'], FinanceTransaction::orderBy('description')->pluck('state')->all());
        $this->assertSame('expired', FinanceTransaction::where('description', 'old')->sole()->end_log['context']['reason']);
        $this->assertTrue((new IntegrityVerifier())->run()->isValid());
    }

    public function test_logs_hold_no_personal_data(): void
    {
        $user = $this->user();
        request()->server->set('REMOTE_ADDR', '203.0.113.77');
        request()->setMethod('POST'); // the payload travels in the POST body, as from a form
        $user->setGatewayPayload(GatewayPayload::make()->setPhoneNumber('237690000000')->setAccountName('Alice Doe')->setPaymentMethod('MTN'))
            ->credit(DefaultPaymentProvider::getId(), 10, 'top up');

        $endLog = FinanceTransaction::sole()->end_log;
        $encoded = json_encode($endLog);
        $this->assertStringNotContainsString('237690000000', $encoded);
        $this->assertStringNotContainsString('Alice Doe', $encoded);
        $this->assertSame('MTN', $endLog['parameters']['____payment_method']);
        $this->assertSame(['203.0.113.0'], $endLog['ips']);
    }

    public function test_gateway_is_found_by_int_or_numeric_string_id(): void
    {
        $provider = FinanceProvider::create(['name' => 'Fake', 'assigned_id' => FakeGateway::getId()]);

        $this->assertInstanceOf(FakeGateway::class, PaymentProviderGateway::load($provider->id));
        $this->assertInstanceOf(FakeGateway::class, PaymentProviderGateway::load((string)$provider->id));
        $this->assertInstanceOf(FakeGateway::class, PaymentProviderGateway::load('FAKE'));

        DB::enableQueryLog();
        PaymentProviderGateway::load('FAKE');
        $this->assertCount(1, DB::getQueryLog(), 'providers are registered with a single query once they exist');
    }

    public function test_providers_endpoint_lists_public_providers(): void
    {
        $this->getJson('/api/finance/providers')->assertOk()->assertJsonPath('success', true)
            ->assertJsonFragment(['provider_id' => 'FAKE']);
    }

    public function test_works_on_a_database_without_the_new_tables_nor_key(): void
    {
        // Host application that upgraded the package but did not publish the new migrations yet
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_delete_finance_chain_entries');
        Schema::drop('finance_chain_entries');
        Schema::drop('finance_audit_logs');
        FinanceSchema::flush();
        config()->set('finance.signature.key', null);

        $user = $this->user();
        $this->assertTrue($user->credit(DefaultPaymentProvider::getId(), 10, 'top up')->isSuccess());
        $this->assertTrue($user->debit(DefaultPaymentProvider::getId(), 4, 'usage')->isSuccess());
        $user->setThreshold(0);

        $this->assertEquals(6, $user->balanceChecksum(true, 'USD'));
        $this->assertSame([2, 2], FinanceTransaction::pluck('signature_version')->map(fn($v) => (int)$v)->all());
        $verifier = (new IntegrityVerifier())->run();
        $this->assertTrue($verifier->isValid(), json_encode($verifier->errors()));
        $this->assertCount(3, array_filter($verifier->warnings(), fn($w) => in_array($w['type'], ['signature', 'chain', 'audit'], true)));
    }

    public function test_ledger_without_currency_is_reused_and_signed(): void
    {
        $partner = $this->partner();
        $first = $partner->wallet('marketplace');
        $second = $partner->wallet('marketplace');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FinanceLedger::count());
        $this->assertStringStartsWith('v3:k1:', $first->checksum);
        $this->assertTrue($first->fresh()->verifyChecksum());
    }
}
