<?php

namespace App\Http\Controllers;

use App\BoletaNotes\Evidence;
use App\BoletaNotes\Preparation;
use App\BoletaNotes\SummaryPreparation;
use App\BoletaNotes\SummaryTransport;
use App\Models\Boleta;
use App\Models\BoletaNote;
use App\Models\NoteSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BoletaNoteController
{
    public function index(Request $r)
    {
        return view('boleta-notes.index', ['boletas' => Boleta::query()->where('user_id', $r->user()->id)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('h4_members')->join('h4_summaries','h4_summaries.id','=','h4_members.summary_id')
                ->whereColumn('h4_members.boleta_id','h4_boletas.id')->where('h4_summaries.state','sim_accepted'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('h5b_notes')->whereColumn('h5b_notes.original_id','h4_boletas.id'))->latest()->limit(50)->get(),
            'notes' => BoletaNote::query()->where('user_id',$r->user()->id)->latest()->limit(50)->get(),
            'summaries' => NoteSummary::query()->where('user_id',$r->user()->id)->latest()->limit(30)->get()]);
    }

    public function issue(Request $r, Preparation $p) { $n = $p->issue($r->user()->id,$r->all()); return redirect()->route('receipt-notes.show',['kind'=>'note','id'=>$n->id],303); }
    public function freeze(Request $r, SummaryPreparation $p) { $s = $p->freeze($r->user()->id,$r->all()); return redirect()->route('receipt-notes.show',['kind'=>'rc','id'=>$s->id],303); }

    public function show(Request $r, string $kind, string $id)
    {
        $record = $this->owned($r,$kind,$id);
        $rc = $kind === 'rc' ? $record : null;
        return view('boleta-notes.show', ['record'=>$record,'kind'=>$kind,'rc'=>$rc,
            'member' => $kind === 'note' ? DB::table('h5b_members')->where('note_id',$id)->first() : null,
            'events'=>$rc ? DB::table('h5b_events')->where('summary_id',$id)->orderBy('id')->get() : []]);
    }

    public function process(Request $r, string $id, SummaryTransport $t)
    {
        $this->owned($r,'rc',$id);
        $v = $r->validate(['action'=>'required|in:send,poll,recover','scenario'=>'nullable|string']);
        match ($v['action']) { 'send'=>$t->send($id,$v['scenario'] ?? 'accepted'), 'poll'=>$t->poll($id,$v['scenario'] ?? '0'), 'recover'=>$t->recover($id) };
        return redirect()->route('receipt-notes.show',['kind'=>'rc','id'=>$id],303);
    }

    public function download(Request $r, string $kind, string $id, string $file, Evidence $files)
    {
        $record=$this->owned($r,$kind,$id); abort_unless(in_array($file,['xml','zip','certificate'],true),404);
        try { $path=$files->path($id,$file,$record->artifacts); } catch (\RuntimeException) { abort(409,'Evidencia ausente/alterada.'); }
        return response()->download($path,'H5B-FICTICIO-'.$id.'.'.match($file){'xml'=>'xml','zip'=>'zip','certificate'=>'pem'});
    }

    private function owned(Request $r,string $kind,string $id): BoletaNote|NoteSummary
    {
        abort_unless(Str::isUuid($id) && in_array($kind,['note','rc'],true),404);
        return ($kind==='note' ? BoletaNote::query() : NoteSummary::query())->where('user_id',$r->user()->id)->findOrFail($id);
    }
}
