<?php

namespace App\Http\Controllers;

use App\Recipients\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};

final class RecipientController
{
    public function index(Request $r, Access $access)
    {
        $grants=DB::table('recipient_document_access')->where('recipient_user_id',$r->user()->id)->whereNull('revoked_at')
            ->orderByDesc('created_at')->orderBy('id')->simplePaginate(20);
        $documents=[];
        foreach($grants as $g) {
            try { [$grant,$d]=$access->assigned($r->user()->id,$g->id);$documents[$g->id]=$access->present($g->kind,$d); }
            catch(\Illuminate\Database\QueryException $e) { throw $e; }
            catch(\RuntimeException) { $documents[$g->id]=null; $access->audit('blocked',$r->user()->id,$g); }
        }
        Log::notice('recipient_list_viewed',['actor'=>$r->user()->id,'entries'=>count($documents)]);
        return view('recipients.index',compact('grants','documents'));
    }

    public function show(Request $r, string $id, Access $access) { return $this->page($r,$id,$access,false); }
    public function print(Request $r, string $id, Access $access) { return $this->page($r,$id,$access,true); }

    private function page(Request $r, string $id, Access $access, bool $print)
    {
        [$grant,$record]=$this->assigned($r,$id,$access);
        try { $document=$access->present($grant->kind,$record); } catch(\RuntimeException) { abort(409,'Documento no verificable; acceso bloqueado.'); }
        $access->audit($print?'print_viewed':'viewed',$r->user()->id,$grant);
        return view('recipients.show',compact('grant','document','print'));
    }

    public function download(Request $r, string $id, string $file, Access $access)
    {
        abort_unless(in_array($file,['xml','zip'],true),404);
        [$grant,$record]=$this->assigned($r,$id,$access);
        try { $path=$access->path($grant->kind,$record,$file); } catch(\RuntimeException) { abort(409,'Archivo no verificable; descarga bloqueada.'); }
        $access->audit('download_'.$file,$r->user()->id,$grant);
        return response()->download($path,'COMPROBANTE-FICTICIO-'.$record->snapshot['series'].'-'.$record->number.'.'.$file);
    }

    private function assigned(Request $r, string $id, Access $access): array
    {
        try { return $access->assigned($r->user()->id,$id); }
        catch(\Illuminate\Database\QueryException|\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\HttpException $e) { throw $e; }
        catch(\RuntimeException) {
            Log::warning('recipient_document_blocked',['actor'=>$r->user()->id,'access_id'=>$id]);
            abort(409,'Documento o evidencia no verificable; acceso bloqueado.');
        }
    }
}
