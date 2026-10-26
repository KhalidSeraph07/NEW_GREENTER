<?php

namespace Tests\H5A;

use App\Notes\Preparation;
use Illuminate\Support\Str;

final class AccessTest extends NoteCase
{
    public function test_minimum_ui_issues_a_note_and_shows_only_a_fictitious_result(): void
    {
        $source = $this->original();
        $this->withBasicAuth($this->operator->email,$this->password)->get('/invoice-notes')->assertOk()->assertSee('H5-A')->assertSee('Sin validez fiscal');
        $input = $this->noteInput($source); $n = app(Preparation::class)->issue($this->operator->id,$input);
        $this->post('/invoice-notes',$input)->assertRedirectToRoute('notes.show',$n->id);
        $this->get('/invoice-notes/'.$n->id)->assertOk()->assertSee('pending')->assertSee('Sin CDR oficial');
        $this->post('/invoice-notes/'.$n->id.'/simulate',['action'=>'dispatch','scenario'=>'accepted'])->assertStatus(303);
        $this->get('/invoice-notes/'.$n->id)->assertOk()->assertSee('sim_accepted')->assertSee('H5A_LOCAL_FAKE');
        $this->get('/invoice-notes/'.$n->id.'/evidence/xml')->assertOk();
        $this->get('/invoice-notes/'.$n->id.'/evidence/zip')->assertOk();
    }

    public function test_cross_owner_original_note_download_and_actions_are_refused(): void
    {
        $source = $this->original(); $n = app(Preparation::class)->issue($this->operator->id,$this->noteInput($source));
        $other = $this->newOperator(); $this->withBasicAuth($other->email,$this->password);
        $this->get('/invoice-notes/'.$n->id)->assertNotFound();
        $this->get('/invoice-notes/'.$n->id.'/evidence/zip')->assertNotFound();
        $this->post('/invoice-notes/'.$n->id.'/simulate',['action'=>'dispatch'])->assertNotFound();
        $this->postJson('/invoice-notes',$this->noteInput($source))->assertNotFound();
        $this->assertSame('pending',$n->fresh()->state);
    }

    public function test_csrf_private_files_and_unimplemented_operations_remain_blocked(): void
    {
        $this->app->instance('env','local'); $this->withBasicAuth($this->operator->email,$this->password);
        $this->post('/invoice-notes',[])->assertStatus(419);
        $this->get('/storage/app/private/h5/test.xml')->assertNotFound();
        $this->get('/invoice-notes/'.Str::uuid().'/evidence/private-key')->assertNotFound();
        $this->put('/invoice-notes/'.Str::uuid(),[])->assertStatus(405);
        $this->get('/boleta-notes')->assertNotFound();
    }
}
