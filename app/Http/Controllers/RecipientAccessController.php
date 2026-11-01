<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Recipients\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

final class RecipientAccessController
{
    public function index(Request $r)
    {
        $filters=Validator::make($r->query(),['kind'=>'nullable|in:invoice,boleta,invoice_note,boleta_note','number'=>'nullable|integer|min:1'])->validate();
        $union=null;
        foreach(Access::MODELS as $kind=>$model) {
            $q=DB::table((new $model)->getTable())->where('user_id',$r->user()->id)->selectRaw("? as kind, id, snapshot->>'series' as series, number, created_at",[$kind]);
            $union=$union?$union->unionAll($q):$q;
        }
        $documents=DB::query()->fromSub($union,'documents')->when($filters['kind'] ?? null,fn($q,$v)=>$q->where('kind',$v))
            ->when($filters['number'] ?? null,fn($q,$v)=>$q->where('number',$v))->orderByDesc('created_at')->orderBy('id')->simplePaginate(20,['*'],'documents_page')->withQueryString();
        $recipients=User::query()->where('role','RECEPTOR')->where('is_active',true)->orderBy('name')->get(['id','name','email']);
        $grants=DB::table('recipient_document_access')->where('owner_user_id',$r->user()->id)->orderByDesc('created_at')->orderBy('id')->simplePaginate(20,['*'],'grants_page')->withQueryString();
        $grantDocuments=[];
        foreach($grants as $g) {
            $model=Access::MODELS[$g->kind];$d=$model::query()->where('user_id',$r->user()->id)->find($g->document_id);
            $grantDocuments[$g->id]=$d?$d->snapshot['series'].'-'.$d->number:'No disponible';
        }
        return view('recipients.access',compact('filters','documents','recipients','grants','grantDocuments'));
    }

    public function store(Request $r, Access $access)
    {
        if(array_diff(array_keys($r->all()),['_token','document','recipient_id','confirmed'])) { throw ValidationException::withMessages(['access'=>'Campos de autorización no permitidos.']); }
        $v=$r->validate(['document'=>'required|string','recipient_id'=>'required|integer|min:1','confirmed'=>'required|accepted']);
        $key=explode(':',$v['document'],2);
        if(count($key)!==2 || !isset(Access::MODELS[$key[0]])) { throw ValidationException::withMessages(['document'=>'Selecciona un comprobante propio.']); }
        try { $g=$access->grant($r->user()->id,(int)$v['recipient_id'],$key[0],$key[1]); }
        catch(\Illuminate\Database\QueryException|\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\HttpException $e) { throw $e; }
        catch(\RuntimeException) { abort(409,'Evidencia no verificable; autorización detenida.'); }
        return redirect()->route('recipient-access.index',[],303)->with('access_recorded',$g->id);
    }

    public function revoke(Request $r, string $id, Access $access)
    {
        $access->revoke($r->user()->id,$id);
        return redirect()->route('recipient-access.index',[],303)->with('access_revoked',$id);
    }
}
