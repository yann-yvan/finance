<?php

namespace NYCorp\Finance\Tests\Feature;

use Illuminate\Support\Facades\Event;
use NYCorp\Finance\Events\FinanceTransactionSuccessEvent;
use NYCorp\Finance\Http\Payment\DefaultPaymentProvider;
use NYCorp\Finance\Http\Payment\DohonePaymentProvider;
use NYCorp\Finance\Models\FinanceAuditLog;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Tests\Fixtures\FakeGateway;
use NYCorp\Finance\Tests\Fixtures\OfflineDohoneGateway;
use NYCorp\Finance\Tests\TestCase;
use NYCorp\Finance\Http\Response\ResponseCode;

class PaymentCallbackTest extends TestCase
{
    private function pendingDeposit(float $amount = 25): FinanceTransaction
    {
        $response = $this->user()->credit(FakeGateway::getId(), $amount, 'mobile money');
        $this->assertTrue($response->isSuccess(), $response->getMessage());
        return FinanceTransaction::sole();
    }

    private function notify(array $data, string $provider = 'FAKE', string $movement = 'deposit')
    {
        return $this->postJson("/api/finance/$movement/notification/$provider", $data);
    }

    public function test_genuine_success_completes_the_deposit(): void
    {
        Event::fake([FinanceTransactionSuccessEvent::class]);
        $transaction = $this->pendingDeposit();

        $this->notify(['ref' => $transaction->id, 'signature' => 'good', 'status' => 'SUCCESS', 'reference' => 'OP-1'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('body.state', 'COMPLETED');

        $transaction->refresh();
        $this->assertSame(FinanceTransaction::STATE_COMPLETED, $transaction->state);
        $this->assertSame('OP-1', $transaction->external_id);
        $this->assertEquals(25, $transaction->wallet->owner->balance);
        Event::assertDispatchedTimes(FinanceTransactionSuccessEvent::class, 1);
    }

    public function test_forged_callback_is_rejected_and_does_not_lock_the_transaction(): void
    {
        $transaction = $this->pendingDeposit();

        $this->notify(['ref' => $transaction->id, 'signature' => 'forged', 'status' => 'FAILED'])
            ->assertOk()->assertJsonPath('success', false)->assertJsonPath('code', abs(ResponseCode::REQUEST_NOT_AUTHORIZED));

        $transaction->refresh();
        $this->assertSame(FinanceTransaction::STATE_PENDING, $transaction->state);
        $this->assertFalse((bool)$transaction->is_locked);
        $this->assertSame(1, FinanceAuditLog::where('event', FinanceAuditLog::EVENT_CALLBACK_REJECTED)->count());

        // The genuine callback can still complete it
        $this->notify(['ref' => $transaction->id, 'signature' => 'good', 'status' => 'SUCCESS'])->assertJsonPath('body.state', 'COMPLETED');
    }

    public function test_reference_without_success_does_not_complete(): void
    {
        $transaction = $this->pendingDeposit();

        // Provider answers "still pending" but with an operator reference (CinetPay-like)
        $this->notify(['ref' => $transaction->id, 'signature' => 'good', 'status' => 'PENDING', 'reference' => 'OP-2'])
            ->assertJsonPath('success', true)->assertJsonPath('body.state', 'PENDING');

        $this->assertSame(FinanceTransaction::STATE_PENDING, $transaction->fresh()->state);
        $this->assertEquals(0, $transaction->fresh()->wallet()->withoutGlobalScopes()->first()->owner->balance);
    }

    public function test_definitive_failure_fails_the_transaction(): void
    {
        $transaction = $this->pendingDeposit();

        $this->notify(['ref' => $transaction->id, 'signature' => 'good', 'status' => 'FAILED'])->assertJsonPath('body.state', 'FAILED');

        $this->assertTrue((bool)$transaction->fresh()->is_locked);
    }

    public function test_repeated_callback_is_idempotent(): void
    {
        Event::fake([FinanceTransactionSuccessEvent::class]);
        $transaction = $this->pendingDeposit();
        $payload = ['ref' => $transaction->id, 'signature' => 'good', 'status' => 'SUCCESS'];

        $this->notify($payload)->assertJsonPath('success', true);
        $this->notify($payload)->assertOk()->assertJsonPath('success', true)->assertJsonPath('body.state', 'COMPLETED');

        $this->assertEquals(25, $transaction->fresh()->wallet->owner->balance);
        Event::assertDispatchedTimes(FinanceTransactionSuccessEvent::class, 1);
    }

    public function test_success_after_cancellation_is_flagged(): void
    {
        $transaction = $this->pendingDeposit();
        $this->travel(8)->days();
        $this->artisan('finance:expire-pending')->assertSuccessful();
        $this->assertSame(FinanceTransaction::STATE_CANCELED, $transaction->fresh()->state);

        $this->notify(['ref' => $transaction->id, 'signature' => 'good', 'status' => 'SUCCESS'])->assertJsonPath('body.state', 'CANCELED');

        $this->assertTrue(FinanceAuditLog::where('event', FinanceAuditLog::EVENT_CALLBACK_REJECTED)
            ->get()->contains(fn($log) => $log->new_values['reason'] === 'success_on_finalized_transaction'));
    }

    public function test_unknown_transaction_is_answered_not_found(): void
    {
        $this->notify(['ref' => 'does-not-exist', 'signature' => 'good', 'status' => 'SUCCESS'])
            ->assertOk()->assertJsonPath('success', false)->assertJsonPath('code', abs(ResponseCode::REQUEST_NOT_FOUND));

        $this->assertSame(0, FinanceTransaction::count());
    }

    public function test_internal_provider_and_unknown_provider_callbacks_change_nothing(): void
    {
        $this->notify(['anything' => 1], DefaultPaymentProvider::getId())->assertOk()->assertJsonPath('success', false);
        $this->notify(['anything' => 1], DohonePaymentProvider::getId(), 'withdrawal')->assertOk()->assertJsonPath('success', false);
        $this->notify(['anything' => 1], 'NOPE')->assertOk()->assertJsonPath('success', false);

        $this->assertSame(0, FinanceTransaction::count());
    }

    public function test_callback_routes_only_accept_get_and_post(): void
    {
        $this->deleteJson('/api/finance/deposit/notification/FAKE')->assertStatus(405);
        $this->getJson('/api/finance/deposit/notification/FAKE?ref=x&signature=good')->assertOk();
    }

    private function dohoneDeposit(float $amount = 10): FinanceTransaction
    {
        // Same callback code as Dohone, without the call to the Dohone API when starting
        config()->set('finance.payment_providers', [DefaultPaymentProvider::class, FakeGateway::class, OfflineDohoneGateway::class]);
        $response = $this->user()->credit(DohonePaymentProvider::getId(), $amount, 'dohone');
        $this->assertTrue($response->isSuccess(), $response->getMessage());
        return FinanceTransaction::sole();
    }

    private function dohonePayload(FinanceTransaction $transaction, int $paid, ?string $hash = null): array
    {
        $payload = ['idReqDoh' => 'DOH-1', 'rI' => $transaction->id, 'rMt' => (string)$paid, 'mode' => 'MTN'];
        $payload['hash'] = $hash ?? md5($payload['idReqDoh'] . $payload['rI'] . $payload['rMt'] . 'dohone-secret');
        return $payload;
    }

    public function test_dohone_valid_hash_completes(): void
    {
        $transaction = $this->dohoneDeposit();

        $this->notify($this->dohonePayload($transaction, 10), 'DHN')->assertJsonPath('body.state', 'COMPLETED');

        $this->assertSame('DOH-1', $transaction->fresh()->external_id);
    }

    public function test_dohone_invalid_hash_is_rejected(): void
    {
        $transaction = $this->dohoneDeposit();

        $this->notify($this->dohonePayload($transaction, 10, md5('forged')), 'DHN')
            ->assertJsonPath('success', false)->assertJsonPath('code', abs(ResponseCode::REQUEST_NOT_AUTHORIZED));
        $this->notify(['rI' => $transaction->id], 'DHN')->assertJsonPath('success', false);

        $this->assertSame(FinanceTransaction::STATE_PENDING, $transaction->fresh()->state);
    }

    public function test_callback_from_another_provider_is_rejected(): void
    {
        $transaction = $this->pendingDeposit();

        $this->notify($this->dohonePayload($transaction, 25), 'DHN')
            ->assertJsonPath('success', false)->assertJsonPath('code', abs(ResponseCode::REQUEST_NOT_AUTHORIZED));

        $this->assertSame(FinanceTransaction::STATE_PENDING, $transaction->fresh()->state);
    }

    public function test_dohone_short_payment_fails(): void
    {
        $transaction = $this->dohoneDeposit(10);

        $this->notify($this->dohonePayload($transaction, 9), 'DHN')->assertJsonPath('body.state', 'FAILED');
    }

    public function test_dohone_withdrawal_notification_never_changes_a_transaction(): void
    {
        $transaction = $this->dohoneDeposit();

        $this->notify($this->dohonePayload($transaction, 10), 'DHN', 'withdrawal')->assertJsonPath('body.state', 'PENDING');
    }

    public function test_sms_verification_is_validated_and_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/finance/dohone-sms-verify')->assertOk()->assertJsonPath('success', false);
        }
        $this->postJson('/api/finance/dohone-sms-verify')->assertStatus(429);
    }
}
