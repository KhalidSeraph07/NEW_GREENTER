<?php

namespace Tests\H64;

use App\Accounts\LocalAccounts;
use App\Models\User;
use App\Recipients\Access;
use Illuminate\Support\Facades\{DB, Log, Route};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\H5C1\VoidCase;

final class RecipientAccessTest extends VoidCase
{
    private string $recipientPassword='H64-Only-Fictitious-Password-7582';
    private function recipient(): User { return app(LocalAccounts::class)->create(['name'=>'Receptor ficticio H64','email'=>Str::uuid().'@example.invalid','role'=>'RECEPTOR','password'=>$this->recipientPassword,'password_confirmation'=>$this->recipientPassword]); }
    private function login(User $user): void
    {
        $this->get('/login')->assertOk();
        $this->post('/login',['email'=>$user->email,'password'=>$user->role==='RECEPTOR'?$this->recipientPassword:$this->password,'_token'=>session()->token()])
            ->assertRedirect($user->role==='RECEPTOR'?'/recipient':'/');
    }
    private function allow(array $key, User $recipient): object { return app(Access::class)->grant($this->operator->id,$recipient->id,$key['kind'],$key['id']); }
    private function form(array $key, User $recipient): array { return ['document'=>$key['kind'].':'.$key['id'],'recipient_id'=>(string)$recipient->id,'confirmed'=>'1']; }
    public static function cases(): array { return [['invoice','NC01'],['boleta','NC01'],['invoice_note','NC01'],['invoice_note','ND02'],['boleta_note','NC01'],['boleta_note','ND02']]; }

    #[DataProvider('cases')]
    public function test_assigned_document_is_read_downloaded_and_printed_without_changing_source(string $kind,string $operation): void
    {
        $k=$this->accepted($kind,$operation);$r=$this->recipient();$a=$this->allow($k,$r);
        $d=app(Access::class)->document($this->operator->id,$kind,$k['id']);$before=$d->attributesToArray();
        $grants=DB::table('h5c1_grants')->where('document_id',$d->id)->get()->toJson();$this->login($r);
        $url='/recipient/'.$a->id;$name=$d->snapshot['series'].'-'.$d->number;
        $this->get('/recipient')->assertOk()->assertSee($name)->assertDontSee('/accounts')->assertDontSee('/invoices/create')->assertDontSee('/voids');
        $this->get($url)->assertOk()->assertSee($name)->assertSee('SIN VALIDEZ FISCAL')->assertSee('Subtotal conservado')->assertDontSee('name="scenario"',false)->assertDontSee('Outbox')->assertDontSee('SIM-H5')->assertDontSee('/summaries/');
        $print=$this->get($url.'/print')->assertOk()->assertSee('Ctrl+P')->assertSee('no es una representación impresa fiscalmente validada');
        $this->assertStringContainsString(\App\Invoices\InvoiceInput::decimal($d->snapshot['total_cents']),$print->getContent());
        $this->assertStringContainsString(\App\Invoices\InvoiceInput::decimal($d->snapshot['quantity_milli'],3),$print->getContent());
        $this->assertStringContainsString($kind==='boleta'||$kind==='boleta_note'?'CONSUMIDOR FICTICIO H4':'RECEPTOR FICTICIO H2',$print->getContent());
        foreach(['xml','zip'] as $file) {
            $response=$this->get($url.'/evidence/'.$file)->assertOk();
            $this->assertSame(hash_file('sha256',app(Access::class)->path($kind,$d,$file)),hash_file('sha256',$response->baseResponse->getFile()->getPathname()));
        }
        foreach(['certificate','unsigned','cdr','pdf'] as $file){$this->get($url.'/evidence/'.$file)->assertNotFound();}
        $this->assertSame($before,$d->fresh()->attributesToArray());
        $this->assertSame($grants,DB::table('h5c1_grants')->where('document_id',$d->id)->get()->toJson());
        $this->assertSame(0,DB::table(match($kind){'invoice'=>'invoices','boleta'=>'h4_boletas','invoice_note'=>'h5_notes','boleta_note'=>'h5b_notes'})->where('user_id',$r->id)->count());
    }

    public function test_admin_creates_receiver_with_protected_password_and_old_operator_does_not_manage_accounts(): void
    {
        $this->operator->forceFill(['role'=>'ADMIN'])->save();$this->login($this->operator);
        $values=['name'=>'Receptor creado por ADMIN','email'=>Str::uuid().'@example.invalid','role'=>'RECEPTOR','password'=>$this->recipientPassword,'password_confirmation'=>$this->recipientPassword];
        $this->get('/accounts')->assertOk()->assertSee('<option>RECEPTOR</option>',false);
        $this->post('/accounts',$values)->assertStatus(303);$r=User::query()->where('email',$values['email'])->sole();
        $this->assertSame('RECEPTOR',$r->role);$this->assertTrue(\Illuminate\Support\Facades\Hash::check($this->recipientPassword,$r->password));$this->assertNotSame($this->recipientPassword,$r->password);
        $this->operator->forceFill(['role'=>'OPERADOR'])->save();$this->post('/accounts',$values)->assertForbidden();
    }

    public function test_receiver_cannot_use_any_operator_or_admin_route_and_logout_is_shared(): void
    {
        $r=$this->recipient();$this->login($r);$checked=0;
        foreach(Route::getRoutes() as $route) {
            $mw=$route->gatherMiddleware();
            if(!in_array('h2.operator',$mw,true) && !in_array('h2.operator:ADMIN',$mw,true)) { continue; }
            $url='/'.ltrim(preg_replace('/\{[^}]+\}/',(string)Str::uuid(),$route->uri()),'/');
            foreach(array_diff($route->methods(),['HEAD']) as $method) {
                $this->json($method,$url,[])->assertForbidden();$checked++;
            }
        }
        $this->assertGreaterThan(40,$checked);$this->get('/recipient')->assertOk();
        $session=session()->getId();$this->post('/logout')->assertRedirect('/login');$this->assertGuest();$this->assertNotSame($session,session()->getId());$this->getJson('/recipient')->assertUnauthorized();
    }

    public function test_two_receivers_with_the_same_synthetic_identity_cannot_copy_each_others_urls(): void
    {
        $k=$this->accepted();$a=$this->recipient();$b=$this->recipient();$grant=$this->allow($k,$a);
        $this->login($b);$this->get('/recipient')->assertOk()->assertDontSee($grant->id)->assertDontSee('F000-');
        foreach(['/recipient/'.$grant->id,'/recipient/'.$grant->id.'/print','/recipient/'.$grant->id.'/evidence/xml','/recipient/'.$grant->id.'/evidence/zip','/recipient/'.$k['id'],'/recipient/'.Str::uuid()] as $url){$this->get($url)->assertNotFound();}
        $this->postJson('/recipient-access',$this->form($k,$b))->assertForbidden();
        $this->login($a);$this->get('/recipient/'.$grant->id)->assertOk();
    }

    public static function owners(): array { return [['ADMIN'],['OPERADOR']]; }
    #[DataProvider('owners')]
    public function test_owner_can_grant_and_revoke_only_own_documents_with_explicit_confirmation(string $role): void
    {
        $k=$this->accepted();$r=$this->recipient();$foreign=$this->newOperator();$foreign->forceFill(['role'=>$role])->save();
        $this->operator->forceFill(['role'=>$role])->save();
        $this->signIn($foreign->email,$this->password);$this->get('/recipient-access')->assertOk()->assertDontSee($k['id']);
        $this->postJson('/recipient-access',$this->form($k,$r))->assertNotFound();
        $this->login($this->operator);$f=$this->form($k,$r);
        $this->postJson('/recipient-access',array_diff_key($f,['confirmed'=>true]))->assertUnprocessable();
        $this->postJson('/recipient-access',$f+['owner_user_id'=>$foreign->id])->assertUnprocessable();
        $this->post('/recipient-access',$f)->assertStatus(303);$this->post('/recipient-access',$f)->assertStatus(303);
        $a=DB::table('recipient_document_access')->where('document_id',$k['id'])->sole();
        $this->signIn($foreign->email,$this->password);$this->post('/recipient-access/'.$a->id.'/revoke')->assertNotFound();
        $this->login($this->operator);$this->post('/recipient-access/'.$a->id.'/revoke')->assertStatus(303);$this->post('/recipient-access/'.$a->id.'/revoke')->assertStatus(303);
        $this->assertNotNull(DB::table('recipient_document_access')->where('id',$a->id)->value('revoked_at'));
        $this->post('/recipient-access',$f)->assertStatus(303);
        $this->assertSame(2,DB::table('recipient_document_access')->where('document_id',$k['id'])->count());
        $this->assertSame($this->operator->id,\App\Models\Invoice::query()->findOrFail($k['id'])->user_id);
    }

    public function test_revocation_and_deactivation_apply_to_an_existing_session_immediately(): void
    {
        $k=$this->accepted();$r=$this->recipient();$a=$this->allow($k,$r);$this->login($r);$this->get('/recipient/'.$a->id)->assertOk();
        app(Access::class)->revoke($this->operator->id,$a->id);
        foreach(['/recipient/'.$a->id,'/recipient/'.$a->id.'/print','/recipient/'.$a->id.'/evidence/zip'] as $url){$this->get($url)->assertNotFound();}
        $this->get('/recipient')->assertOk()->assertDontSee($a->id);
        $b=$this->allow($k,$r);$this->get('/recipient/'.$b->id)->assertOk();app(LocalAccounts::class)->deactivate($r->id);
        $this->getJson('/recipient/'.$b->id)->assertUnauthorized();$this->assertGuest();
        $this->postJson('/login',['email'=>$r->email,'password'=>$this->recipientPassword])->assertUnprocessable();
    }

    public function test_owner_mismatch_in_a_new_authorization_is_rejected_without_transferring_document(): void
    {
        $k=$this->accepted();$r=$this->recipient();$a=$this->allow($k,$r);$other=$this->newOperator();
        DB::table('recipient_document_access')->where('id',$a->id)->update(['owner_user_id'=>$other->id]);
        $this->login($r);$this->get('/recipient')->assertOk()->assertSee('Documento bloqueado')->assertDontSee('F000-');
        foreach(['/recipient/'.$a->id,'/recipient/'.$a->id.'/print','/recipient/'.$a->id.'/evidence/xml'] as $url){$this->get($url)->assertNotFound();}
        $this->assertSame($this->operator->id,\App\Models\Invoice::query()->findOrFail($k['id'])->user_id);
    }

    public function test_altered_new_file_blocks_list_detail_print_and_both_downloads_without_repair(): void
    {
        $k=$this->accepted('boleta');$r=$this->recipient();$a=$this->allow($k,$r);$d=app(Access::class)->document($this->operator->id,$k['kind'],$k['id']);
        $path=app(Access::class)->path($k['kind'],$d,'xml');file_put_contents($path,'H64 ALTERED NEW FIXTURE');$this->login($r);
        $this->get('/recipient')->assertOk()->assertSee('Documento bloqueado')->assertDontSee('B000-');
        foreach(['/recipient/'.$a->id,'/recipient/'.$a->id.'/print','/recipient/'.$a->id.'/evidence/xml','/recipient/'.$a->id.'/evidence/zip'] as $url){$this->get($url)->assertConflict();}
        $this->assertSame('H64 ALTERED NEW FIXTURE',file_get_contents($path));
        $this->login($this->operator);$this->postJson('/recipient-access',$this->form($k,$r))->assertConflict();
        $this->post('/recipient-access/'.$a->id.'/revoke')->assertStatus(303);
    }

    public function test_sessions_credentials_and_csrf_keep_their_existing_guarantees(): void
    {
        $r=$this->recipient();$hash=$r->password;$before=session()->getId();$this->login($r);
        $this->assertNotSame($before,session()->getId());$this->assertSame($hash,$r->fresh()->password);
        $this->app->instance('env','local');$this->post('/logout',[])->assertStatus(419);$this->post('/recipient-access',[])->assertStatus(419);
        $this->post('/recipient-access/'.Str::uuid().'/revoke',[])->assertStatus(419);
    }

    public function test_expired_file_session_cannot_restore_recipient_access(): void
    {
        $r=$this->recipient();$this->login($r);$id=session()->getId();session()->save();
        $file=config('session.files').'/'.$id;$this->assertFileExists($file);touch($file,now()->subMinutes(31)->timestamp);
        $this->refreshApplication();$this->withCookie(config('session.cookie'),$id)->getJson('/recipient')->assertUnauthorized();
    }

    public function test_access_logs_are_minimal_and_do_not_include_credentials_or_document_contents(): void
    {
        $handler=new \Monolog\Handler\TestHandler();Log::swap(new \Illuminate\Log\Logger(new \Monolog\Logger('h64',[$handler])));
        $k=$this->accepted();$r=$this->recipient();$a=$this->allow($k,$r);$this->login($r);
        $this->get('/recipient/'.$a->id)->assertOk();$this->get('/recipient/'.$a->id.'/print')->assertOk();$this->get('/recipient/'.$a->id.'/evidence/xml')->assertOk();
        app(Access::class)->revoke($this->operator->id,$a->id);
        $records=json_encode($handler->getRecords());
        foreach(['recipient_access_granted','recipient_access_viewed','recipient_access_print_viewed','recipient_access_download_xml','recipient_access_revoked'] as $event){$this->assertStringContainsString($event,$records);}
        foreach([$this->recipientPassword,$r->email,'<?xml','PRODUCTO FICTICIO H2'] as $secret){$this->assertStringNotContainsString($secret,$records);}
    }
}
