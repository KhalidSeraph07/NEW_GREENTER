<?php
namespace Tests\H5C2;
use App\Corrections\Family;
use Illuminate\Support\Facades\DB;
final class ReaderTest extends CaseBase
{
    public function test_existing_form_and_document_links_use_effective_rc_but_history_stays_rejected(): void
    {
        $r=$this->root('h4');$n=$this->child('h4',$r);$id=$r->snapshot['members'][0]['id'];$t=$this->transport('h4');$t->send($n->id);$t->poll($n->id);
        $this->withBasicAuth($this->operator->email,$this->password)->get('/receipt-notes')->assertOk()->assertSee('B000-'.$r->snapshot['members'][0]['number']);
        $this->get('/boletas/'.$id)->assertOk()->assertSee('sim_accepted')->assertSee('/summaries/'.$n->id);
        $this->get('/summaries/'.$r->id)->assertOk()->assertSee('sim_rejected');
        $this->assertSame($r->id,DB::table('h4_members')->where('boleta_id',$id)->value('summary_id'));
        $rc=$this->root('h5b');$child=$this->child('h5b',$rc);$t=$this->transport('h5b');$t->send($child->id);$t->poll($child->id);
        $note=$rc->snapshot['members'][0]['id'];$this->get('/receipt-notes/note/'.$note)->assertOk()->assertSee('/receipt-notes/rc/'.$child->id);
        $this->get('/receipt-notes/rc/'.$rc->id)->assertOk()->assertSee('sim_rejected');
    }
}
