<?php

namespace Tests\H5A;

use App\Notes\Preparation;
use App\Notes\Transport;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

final class TransportTest extends NoteCase
{
    public static function outcomes(): array
    {
        return [['accepted','sim_accepted'],['rejected','sim_rejected'],['uncertain','uncertain'],['timeout','uncertain'],
            ['incomplete','uncertain'],['wrong_reference','uncertain'],['wrong_hash','uncertain'],['non_simulated','uncertain'],['unexpected_error','uncertain']];
    }

    #[DataProvider('outcomes')]
    public function test_only_correlated_fictitious_results_change_state_and_never_resend(string $scenario, string $expected): void
    {
        $original = $this->original(); $before = $original->getRawOriginal();
        $note = app(Preparation::class)->issue($this->operator->id, $this->noteInput($original));
        $result = app(Transport::class)->dispatch($note->id, $scenario);
        $this->assertSame($expected, $result->state);
        $this->assertNull(app(Transport::class)->dispatch($note->id));
        $this->assertSame(1, DB::table('h5_outbox')->where('note_id', $note->id)->value('attempts'));
        $this->assertSame($before, $original->fresh()->getRawOriginal());
        $this->assertSame(\App\Notes\Original::hash($note->artifacts), \App\Notes\Original::hash($result->artifacts));
    }

    public function test_timeout_recovers_local_fake_receipt_without_another_attempt_even_after_deadline(): void
    {
        $note = app(Preparation::class)->issue($this->operator->id, $this->noteInput($this->original()));
        $this->assertSame('uncertain', app(Transport::class)->dispatch($note->id, 'timeout')->state);
        $this->travel(5)->days();
        $this->assertSame('sim_accepted', app(Transport::class)->recover($note->id)->state);
        $this->assertSame(1, DB::table('h5_outbox')->where('note_id', $note->id)->value('attempts'));
    }

    public function test_only_proven_non_processing_is_retried_with_wait_and_maximum_three(): void
    {
        $note = app(Preparation::class)->issue($this->operator->id, $this->noteInput($this->original()));
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->assertSame($attempt === 3 ? 'failed' : 'retry_wait', app(Transport::class)->dispatch($note->id, 'not_processed')->state);
            $this->assertNull(app(Transport::class)->dispatch($note->id));
            $this->travel(20)->seconds();
        }
        $this->assertSame(0, DB::table('h5_receipts')->where('note_id', $note->id)->count());
        $this->assertSame(3, DB::table('h5_outbox')->where('note_id', $note->id)->value('attempts'));
    }

    public function test_deadline_checked_again_before_send_but_does_not_reject_by_silence(): void
    {
        $note = app(Preparation::class)->issue($this->operator->id, $this->noteInput($this->original()));
        $this->travel(4)->days();
        $this->assertSame('expired', app(Transport::class)->dispatch($note->id)->state);
        $this->assertSame(0, DB::table('h5_outbox')->where('note_id', $note->id)->value('attempts'));
        $this->assertNull($note->fresh()->result);
    }
}
