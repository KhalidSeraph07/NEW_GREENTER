"""Ensayo H7-1 por HTTP loopback y CLI con entrada de prueba; nunca imprime secretos."""
import argparse
import json
import runpy
import secrets
import subprocess
import sys
import urllib.parse
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('--output-dir',type=Path,required=True);p.add_argument('--focused',action='store_true');args=p.parse_args()
args.output_dir.mkdir(parents=True,exist_ok=False)
sys.argv=[str(ROOT/'tests/h64-http-smoke.py'),'--output-dir',str(args.output_dir/'legacy')]+(['--focused'] if args.focused else [])
legacy=runpy.run_path(str(ROOT/'tests/h64-http-smoke.py'));http=legacy['http'];client=legacy['client'];login=legacy['login'];Forms=legacy['Forms'];request=legacy['request']
accounts=legacy['accounts'];checks={}
def check(name,condition):
    assert condition,name
    checks[name]=True
def fresh_password():return 'H71-Aa-'+secrets.token_hex(20)

admin=accounts['admin'];receiver=accounts['B'];disabled=accounts['A'];new_passwords={name:fresh_password() for name in ['admin','receiver','operator']}
private={'accounts':accounts,'new_passwords':new_passwords}
(args.output_dir/'accounts.private.json').write_text(json.dumps(private),encoding='utf-8')

# CommandTester emula exclusivamente la entrada interactiva oculta en este ensayo. La vía real se prueba además con TTY local.
php=r'''require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local')||config('h2.mode')!=='fictitious'||config('database.connections.pgsql.host')!=='postgres'||config('database.connections.pgsql.database')!=='emisor_h1'){exit(2);}
$v=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$u=App\Models\User::query()->findOrFail($v['id']);$before=$u->getAttributes();
$count=App\Models\User::query()->where('role','ADMIN')->where('is_active',true)->count();
$c=new App\Console\Commands\RecoverLocalAccess();$c->setLaravel($app);$t=new Symfony\Component\Console\Tester\CommandTester($c);$t->setInputs(['yes',$v['password'],$v['password']]);
$t->execute(['--user-id'=>(string)$u->id,'--authorized-by'=>'RESPONSABLE-ENSAYO-HTTP-H71'],['interactive'=>true]);$u=$u->fresh();
$a=Illuminate\Support\Facades\DB::table('local_access_recoveries')->where('user_id',$u->id)->orderByDesc('created_at')->first();
$after=$u->getAttributes();unset($before['password'],$before['credential_version'],$after['password'],$after['credential_version']);
echo json_encode(['status'=>$t->getStatusCode(),'id'=>$u->id,'audit'=>$a->id,'outcome'=>$a->outcome,'version'=>$u->credential_version,'active_admins_before'=>$count,
'attributes_preserved'=>$before===$after,'no_secrets_in_output'=>!str_contains($t->getDisplay(),$v['password'])&&!str_contains($t->getDisplay(),$u->password)]);'''
def recover(account,password):
    r=subprocess.run(['docker','compose','run','--rm','-T','php','php','-r',php],input=json.dumps({'id':account['id'],'password':password}),capture_output=True,text=True,cwd=ROOT)
    assert r.returncode==0,'No se obtuvo resultado de la consola del ensayo'
    return json.loads(r.stdout)

admin_a=client();admin_b=client();recipient=client();other=client()
assert login(admin_a,admin)[0]==200 and login(admin_b,admin)[0]==200 and login(recipient,receiver)[0]==200
env=dict(line.split('=',1) for line in (ROOT/'.env').read_text(encoding='utf-8-sig').splitlines() if '=' in line and not line.startswith('#'))
initial={'email':'operador.h2@example.invalid','password':env['H2_OPERATOR_PASSWORD']};assert login(other,initial)[0]==200
check('receiver_cannot_manage_accounts',http(recipient,'/accounts',json_body=True)[0]==403)
result_admin=recover(admin,new_passwords['admin'])
check('only_admin_can_be_recovered_without_another_admin',result_admin['status']==0 and result_admin['active_admins_before']==1 and result_admin['attributes_preserved'])
check('admin_recovery_output_has_no_secrets',result_admin['no_secrets_in_output'])
check('both_old_admin_profiles_rejected',http(admin_a,'/accounts',json_body=True)[0]==401 and http(admin_b,'/accounts',json_body=True)[0]==401)
check('other_users_sessions_survive_admin_recovery',http(recipient,'/recipient')[0]==200 and http(other,'/')[0]==200)
check('old_admin_password_rejected',urllib.parse.urlsplit(login(admin_a,admin)[2]).path=='/login')
admin_new=admin|{'password':new_passwords['admin']};check('new_admin_password_works',login(admin_a,admin_new)[0]==200 and http(admin_a,'/accounts')[0]==200)

_,body,_=http(admin_a,'/accounts');operator={'email':secrets.token_hex(12)+'@example.invalid','name':'Operador recuperable ensayo H71','password':fresh_password()}
f=Forms(body).forms['/accounts']|operator|{'role':'OPERADOR','password_confirmation':operator['password']};assert http(admin_a,'/accounts',f)[0]==200
_,body,_=http(admin_a,'/accounts');row=body.split(operator['email'].encode(),1)[1].split(b'</tr>',1)[0]
import re
operator['id']=int(re.search(rb'/accounts/(\d+)/deactivate',row)[1]);private['operator']=operator
(args.output_dir/'accounts.private.json').write_text(json.dumps(private),encoding='utf-8')
opclient=client();assert login(opclient,operator)[0]==200
result_op=recover(operator,new_passwords['operator'])
check('operator_recovery_preserves_role_and_status',result_op['status']==0 and result_op['attributes_preserved'])
check('old_operator_session_invalidated',http(opclient,'/',json_body=True)[0]==401)
check('operator_new_login_without_account_privileges',login(opclient,operator|{'password':new_passwords['operator']})[0]==200 and http(opclient,'/accounts',json_body=True)[0]==403)
check('initial_operator_session_unchanged',http(other,'/')[0]==200)

result_recipient=recover(receiver,new_passwords['receiver'])
check('receiver_recovery_preserves_role_and_status',result_recipient['status']==0 and result_recipient['attributes_preserved'])
check('old_receiver_session_invalidated',http(recipient,'/recipient',json_body=True)[0]==401)
check('receiver_new_login_is_read_only',login(recipient,receiver|{'password':new_passwords['receiver']})[0]==200 and http(recipient,'/accounts',json_body=True)[0]==403)
check('admin_session_survives_receiver_recovery',http(admin_a,'/accounts')[0]==200)
failed=recover(disabled,fresh_password())
check('disabled_account_not_reactivated_or_modified',failed['status']==1 and failed['attributes_preserved'] and failed['outcome']=='rejected')
check('all_recoveries_have_correlated_durable_results',all(r['outcome']=='completed' and r['version']==1 and r['no_secrets_in_output'] for r in [result_admin,result_op,result_recipient]))
result={'scope':'H7-1 RECUPERACION FICTICIA LOCAL SIN SUNAT','legacy_checks':38 if args.focused else 199,'checks':checks,'recoveries':[result_admin,result_op,result_recipient],'rejected':failed}
(args.output_dir/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))
