<?php
namespace Tests\H71;

use App\Accounts\LocalAccounts;
use App\Console\Commands\{RecoverLocalAccess,BootstrapLocalAdmin};
use App\Models\User;
use Illuminate\Support\Facades\{Artisan,DB,Event,Hash};
use Symfony\Component\Console\Tester\CommandTester;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RecoveryTest extends TestCase
{
    private string $newPassword='H71-New-Only-Test-Password-49382';
    protected function newOperator(): User { return parent::newOperator()->fresh(); }
    private function command(User|int $user, ?array $inputs=null, array $extra=[], bool $interactive=true): CommandTester
    {
        $command=new RecoverLocalAccess();$command->setLaravel($this->app);$test=new CommandTester($command);
        $test->setInputs($inputs ?? ['yes',$this->newPassword,$this->newPassword]);
        $test->execute($extra+['--user-id'=>(string)($user instanceof User?$user->id:$user),'--authorized-by'=>'RESPONSABLE-ENSAYO-H71'],['interactive'=>$interactive]);
        return $test;
    }
    private function begin(User $user): string { return app(LocalAccounts::class)->startRecovery($user->id,'RESPONSABLE-ENSAYO-H71')[1]; }
    private function assertPreserved(User $user,array $before): void
    {
        $after=$user->fresh()->getAttributes();unset($before['password'],$before['credential_version'],$after['password'],$after['credential_version']);
        $this->assertSame($before,$after);
    }
    public static function roles(): array { return [['ADMIN'],['OPERADOR'],['RECEPTOR']]; }
    #[DataProvider('roles')]
    public function test_each_existing_role_is_recovered_without_creation_promotion_or_other_attribute_changes(string $role): void
    {
        $this->operator->forceFill(['role'=>$role])->save();$u=$this->operator->fresh();$before=$u->getAttributes();$count=User::query()->count();
        $t=$this->command($u);$this->assertSame(0,$t->getStatusCode());$this->assertStringContainsString('Rol '.$role,$t->getDisplay());
        foreach([$this->newPassword,$u->password,$this->password] as $secret){$this->assertStringNotContainsString($secret,$t->getDisplay());}
        $u=$u->fresh();$this->assertTrue(Hash::check($this->newPassword,$u->password));$this->assertFalse(Hash::check($this->password,$u->password));
        $this->assertSame(1,$u->credential_version);$this->assertSame($count,User::query()->count());$this->assertPreserved($u,$before);
        $a=DB::table('local_access_recoveries')->where('user_id',$u->id)->sole();
        $this->assertSame('completed',$a->outcome);$this->assertSame(0,$a->version_before);$this->assertSame(1,$a->version_after);
        $this->assertSame('RESPONSABLE-ENSAYO-H71',$a->authorized_by_declared);$this->assertNotNull($a->finished_at);
        $trace=json_encode($a);foreach([$this->newPassword,$u->password,$before['password']] as $secret){$this->assertStringNotContainsString($secret,$trace);}
    }

    public function test_unknown_disabled_cancelled_and_forbidden_options_do_not_change_or_create_accounts(): void
    {
        $count=User::query()->count();$unknown=(int)DB::table('users')->max('id')+100000;
        $this->assertSame(1,$this->command($unknown)->getStatusCode());$this->assertSame($count,User::query()->count());
        $this->assertSame('unknown_account',DB::table('local_access_recoveries')->where('requested_user_id',$unknown)->sole()->reason_code);
        $u=$this->operator;$u->forceFill(['is_active'=>false])->save();$before=$u->fresh()->getAttributes();
        $this->assertSame(1,$this->command($u)->getStatusCode());$this->assertSame($before,$u->fresh()->getAttributes());
        foreach([['--role'=>'ADMIN'],['--activate'=>true],['--password'=>$this->newPassword]] as $extra) {
            $t=$this->command($u,extra:$extra);$this->assertSame(1,$t->getStatusCode());$this->assertStringNotContainsString($this->newPassword,$t->getDisplay());
        }
        $u=$this->newOperator();$before=$u->getAttributes();$this->assertSame(1,$this->command($u,['no'])->getStatusCode());
        $this->assertSame('cancelled',DB::table('local_access_recoveries')->where('user_id',$u->id)->sole()->outcome);
        $this->assertSame($before,$u->fresh()->getAttributes());
    }

    public function test_interactive_and_environment_guards_and_password_policy_have_no_partial_effects(): void
    {
        $u=$this->operator;$before=$u->getAttributes();
        $this->assertSame(1,$this->command($u,interactive:false)->getStatusCode());
        foreach([['yes','weak','weak'],['yes',$this->newPassword,'different'],['yes',str_repeat('Aa1',25),str_repeat('Aa1',25)]] as $inputs){
            $this->assertSame(1,$this->command($u,$inputs)->getStatusCode());$this->assertSame($before,$u->fresh()->getAttributes());
        }
        $this->app->instance('env','production');$this->assertSame(1,$this->command($u)->getStatusCode());
        $this->assertSame($before,$u->fresh()->getAttributes());$this->app->instance('env','testing');
        config(['h2.mode'=>'production']);$this->assertSame(1,$this->command($u)->getStatusCode());$this->assertSame($before,$u->fresh()->getAttributes());
    }

    public function test_old_sessions_are_rejected_others_survive_and_new_login_uses_the_new_password(): void
    {
        $u=$this->operator;$other=$this->newOperator();$this->signIn($u->email,$this->password);$this->get('/')->assertOk();
        $this->assertSame(0,$this->command($other)->getStatusCode());$this->get('/')->assertOk();
        $this->assertSame(0,$this->command($u)->getStatusCode());$this->getJson('/')->assertUnauthorized();$this->assertGuest();
        $this->postJson('/login',['email'=>$u->email,'password'=>$this->password])->assertUnprocessable();
        $this->signIn($u->email,$this->newPassword);$this->get('/')->assertOk();
        $this->assertSame(1,session('credential_version'));
    }

    public function test_reusing_current_password_and_state_changes_after_identification_are_rejected(): void
    {
        $u=$this->operator;$this->assertSame(0,$this->command($u)->getStatusCode());$u=$u->fresh();$before=$u->getAttributes();
        $this->assertSame(1,$this->command($u)->getStatusCode());$this->assertSame($before,$u->fresh()->getAttributes());
        $this->assertSame(1,DB::table('local_access_recoveries')->where('user_id',$u->id)->where('reason_code','password_reused')->count());
        $a=$this->begin($u);app(LocalAccounts::class)->deactivate($u->id);$after=$u->fresh()->getAttributes();
        try { app(LocalAccounts::class)->recover($u,$a,'H71-State-Changed-Password-34567','H71-State-Changed-Password-34567');$this->fail('Cambio de estado ignorado'); }
        catch(\Symfony\Component\HttpKernel\Exception\ConflictHttpException){$this->assertSame($after,$u->fresh()->getAttributes());}
        $this->assertSame('account_changed',DB::table('local_access_recoveries')->where('id',$a)->value('reason_code'));
    }

    public function test_receiver_session_is_invalidated_without_changing_document_or_read_authorization(): void
    {
        $invoice=$this->issue();$r=app(LocalAccounts::class)->create(['name'=>'Receptor ensayo H71','email'=>\Illuminate\Support\Str::uuid().'@example.invalid',
            'role'=>'RECEPTOR','password'=>$this->newPassword,'password_confirmation'=>$this->newPassword]);
        $g=app(\App\Recipients\Access::class)->grant($this->operator->id,$r->id,'invoice',$invoice->id);
        $before=json_encode($g);$document=$invoice->fresh()->getAttributes();
        $this->get('/login');$this->post('/login',['email'=>$r->email,'password'=>$this->newPassword])->assertRedirect('/recipient');
        $this->get('/recipient/'.$g->id)->assertOk();$next='H71-Receiver-Second-Password-13579';
        $this->assertSame(0,$this->command($r,['yes',$next,$next])->getStatusCode());
        $this->getJson('/recipient/'.$g->id.'/evidence/xml')->assertUnauthorized();
        $this->post('/login',['email'=>$r->email,'password'=>$next])->assertRedirect('/recipient');$this->get('/recipient/'.$g->id)->assertOk();
        $this->assertSame($before,json_encode(DB::table('recipient_document_access')->where('id',$g->id)->sole()));
        $this->assertSame($document,$invoice->fresh()->getAttributes());$this->assertSame($this->operator->id,$invoice->fresh()->user_id);
    }

    public function test_legacy_session_without_version_is_invalidated_once_and_login_race_cannot_adopt_a_new_version(): void
    {
        $u=$this->operator;$this->signIn($u->email,$this->password);session()->forget('credential_version');
        $this->getJson('/')->assertUnauthorized();$this->signIn($u->email,$this->password);$this->get('/')->assertOk();$this->post('/logout');
        Event::listen(\Illuminate\Auth\Events\Login::class,function()use($u){$a=$this->begin($u);app(LocalAccounts::class)->recover($u,$a,$this->newPassword,$this->newPassword);});
        $this->post('/login',['email'=>$u->email,'password'=>$this->password])->assertRedirect('/');
        $this->assertSame(0,session('credential_version'));$this->getJson('/')->assertUnauthorized();
    }

    public function test_login_fails_closed_when_the_database_version_is_not_available(): void
    {
        $scopes=User::getAllGlobalScopes();
        User::addGlobalScope('h71_missing_version',fn($q)=>$q->select(['id','name','email','password','role','is_active']));
        try {
            $this->postJson('/login',['email'=>$this->operator->email,'password'=>$this->password])->assertUnprocessable();
            $this->assertGuest();$this->getJson('/')->assertUnauthorized();
        } finally { User::setAllGlobalScopes($scopes); }
    }

    public function test_setup_accepts_authorized_recovery_but_rejects_unlogged_password_changes_and_second_bootstrap(): void
    {
        $initial='H71-Initial-Operator-Password-24680';$u=app(LocalAccounts::class)->create(['name'=>'Operador inicial ensayo H71','email'=>\Illuminate\Support\Str::uuid().'@example.invalid',
            'role'=>'OPERADOR','password'=>$initial,'password_confirmation'=>$initial]);
        config(['h2.operator_email'=>$u->email,'h2.operator_password'=>$initial]);$this->assertSame(0,Artisan::call('h2:setup'));
        $this->assertSame(0,$this->command($u)->getStatusCode());$after=$u->fresh()->getAttributes();
        $this->assertSame(0,Artisan::call('h2:setup'));$this->assertSame($after,$u->fresh()->getAttributes());
        DB::table('users')->where('id',$u->id)->update(['password'=>Hash::make('H71-Unauthorised-Change-Password-789')]);
        $this->assertSame(2,$u->fresh()->credential_version);
        try { Artisan::call('h2:setup');$this->fail('Cambio sin auditoría fue aceptado'); }catch(\RuntimeException $e){$this->assertStringContainsString('no coincide',$e->getMessage());}
        $admin=$this->newOperator();$admin->forceFill(['role'=>'ADMIN'])->save();$before=$admin->fresh()->getAttributes();
        $c=new BootstrapLocalAdmin();$c->setLaravel($this->app);$t=new CommandTester($c);$t->setInputs([$this->newPassword,$this->newPassword]);
        $t->execute(['--email'=>$admin->email,'--name'=>'No sobrescribir'],['interactive'=>true]);$this->assertSame(1,$t->getStatusCode());
        $this->assertSame($before,$admin->fresh()->getAttributes());
    }

    public function test_audit_persistence_failure_rolls_back_password_and_version_and_terminal_history_cannot_be_rewritten(): void
    {
        $u=$this->operator;$a=$this->begin($u);$before=$u->getAttributes();
        DB::unprepared("CREATE FUNCTION h71_test_fail_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.outcome='completed' THEN RAISE EXCEPTION 'Synthetic persistence failure'; END IF; RETURN NEW; END $$; CREATE TRIGGER h71_test_fail_audit BEFORE UPDATE ON local_access_recoveries FOR EACH ROW EXECUTE FUNCTION h71_test_fail_audit();");
        try { app(LocalAccounts::class)->recover($u,$a,$this->newPassword,$this->newPassword);$this->fail('Persistencia fallida aceptada'); }
        catch(\Illuminate\Database\QueryException $e){$this->assertSame($before,$u->fresh()->getAttributes());}
        finally { DB::unprepared('DROP TRIGGER h71_test_fail_audit ON local_access_recoveries; DROP FUNCTION h71_test_fail_audit();'); }
        $this->assertSame('failed',DB::table('local_access_recoveries')->where('id',$a)->value('outcome'));
        foreach([
            fn()=>DB::table('local_access_recoveries')->where('id',$a)->update(['outcome'=>'completed']),
            fn()=>DB::table('users')->where('id',$u->id)->update(['credential_version'=>0+9]),
        ] as $operation){try{$operation();$this->fail('Manipulación aceptada');}catch(\Illuminate\Database\QueryException){$this->addToAssertionCount(1);}}
    }
}
