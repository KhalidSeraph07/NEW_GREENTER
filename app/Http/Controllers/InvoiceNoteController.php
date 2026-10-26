<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceNote;
use App\Notes\Evidence;
use App\Notes\Preparation;
use App\Notes\Transport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InvoiceNoteController
{
    public function index(Request $r)
    {
        return view('notes.index', ['invoices' => Invoice::query()->where('user_id',$r->user()->id)->where('transport_status','simulated_accepted')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('h5_notes')->whereColumn('h5_notes.original_id','invoices.id'))->latest()->limit(50)->get(),
            'notes' => InvoiceNote::query()->where('user_id',$r->user()->id)->latest()->limit(30)->get()]);
    }

    public function store(Request $r, Preparation $preparation)
    {
        $n = $preparation->issue($r->user()->id, $r->all());
        return redirect()->route('notes.show',$n->id,303);
    }

    public function show(Request $r, string $id)
    {
        $n = $this->owned($r,$id);
        return view('notes.show', ['note'=>$n,'events'=>DB::table('h5_events')->where('note_id',$id)->orderBy('id')->get()]);
    }

    public function process(Request $r, string $id, Transport $transport)
    {
        $n = $this->owned($r,$id);
        $v = $r->validate(['action'=>'required|in:dispatch,recover','scenario'=>'nullable|string']);
        $v['action'] === 'recover' ? $transport->recover($n->id) : $transport->dispatch($n->id,$v['scenario'] ?? 'accepted');
        return redirect()->route('notes.show',$id,303);
    }

    public function download(Request $r, string $id, string $kind, Evidence $files)
    {
        $n = $this->owned($r,$id); abort_unless(in_array($kind,['xml','zip','certificate'],true),404);
        try { $path = $files->path($n->id,$kind,$n->artifacts); }
        catch (\RuntimeException) { abort(409,'Evidencia H5-A ausente/alterada.'); }
        return response()->download($path,'H5A-FICTICIO-'.$id.'.'.match ($kind) {'xml'=>'xml','zip'=>'zip','certificate'=>'pem'});
    }

    private function owned(Request $r, string $id): InvoiceNote
    {
        abort_unless(Str::isUuid($id),404);
        return InvoiceNote::query()->where('user_id',$r->user()->id)->findOrFail($id);
    }
}
