<?php

namespace Tests\H5B;

use App\BoletaNotes\Preparation;
use App\Models\NoteSummary;

final class AccessTest extends NoteCase
{
    public function test_minimum_note_rc_ui_and_private_evidence_use_only_simulated_results(): void
    {
        $b=$this->original();$input=$this->noteInput($b);
        $this->signIn($this->operator->email,$this->password)->get('/receipt-notes')->assertOk()->assertSee('H5-B')->assertSee('Sin validez fiscal');
        $n=app(Preparation::class)->issue($this->operator->id,$input);
        $this->post('/receipt-notes',$input)->assertStatus(303);
        $this->get('/receipt-notes/note/'.$n->id)->assertOk()->assertSee('prepared')->assertSee('Sin CDR oficial');
        $s=$this->summary([$n->id]);
        $this->post('/note-summaries/'.$s->id.'/simulate',['action'=>'send'])->assertStatus(303);
        $this->get('/receipt-notes/rc/'.$s->id)->assertOk()->assertSee('SIM-H5B-');
        $this->post('/note-summaries/'.$s->id.'/simulate',['action'=>'poll','scenario'=>'0'])->assertStatus(303);
        $this->get('/receipt-notes/rc/'.$s->id)->assertOk()->assertSee('sim_accepted')->assertSee('H5B_LOCAL_FAKE');
        foreach([['note',$n],['rc',$s]] as [$kind,$record]) {
            $this->get('/receipt-notes/'.$kind.'/'.$record->id.'/evidence/xml')->assertOk();
            $this->get('/receipt-notes/'.$kind.'/'.$record->id.'/evidence/zip')->assertOk();
        }
    }

    public function test_foreign_notes_originals_summaries_downloads_and_actions_are_refused(): void
    {
        $b=$this->original();$n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($b));$s=$this->summary([$n->id]);
        $other=$this->newOperator();$this->signIn($other->email,$this->password);
        $this->postJson('/receipt-notes',$this->noteInput($b))->assertNotFound();
        $this->postJson('/note-summaries',['members'=>[$n->id],'idempotency_key'=>(string)\Illuminate\Support\Str::uuid()])->assertUnprocessable();
        foreach([['note',$n],['rc',$s]] as [$kind,$record]) {
            $this->get('/receipt-notes/'.$kind.'/'.$record->id)->assertNotFound();
            $this->get('/receipt-notes/'.$kind.'/'.$record->id.'/evidence/zip')->assertNotFound();
        }
        $this->post('/note-summaries/'.$s->id.'/simulate',['action'=>'send'])->assertNotFound();
        $this->assertSame('pending_send',$s->fresh()->state);
    }

    public function test_csrf_and_private_files_and_individual_note_send_are_unavailable(): void
    {
        $this->app->instance('env','local');$this->signIn($this->operator->email,$this->password);
        $this->post('/receipt-notes',[])->assertStatus(419);$this->post('/note-summaries',[])->assertStatus(419);
        $this->get('/storage/app/private/h5b/test.xml')->assertNotFound();
        $this->post('/receipt-notes/note/'.\Illuminate\Support\Str::uuid().'/simulate',[])->assertNotFound();
    }
}
