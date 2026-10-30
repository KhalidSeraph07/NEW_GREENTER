<?php
namespace Tests\H6;

use App\Accounts\LocalAccounts;
use App\Models\User;
use Illuminate\Support\Facades\{Auth, DB, Hash, RateLimiter};
use Tests\TestCase;

final class AccountsTest extends TestCase
{
    private string $newPassword = 'H6-Only-Local-Password-4826';
    protected function setUp(): void { parent::setUp(); DB::beginTransaction(); }
    protected function tearDown(): void { DB::rollBack(); parent::tearDown(); }
    private function account(string $role='ADMIN'): User
    {
        return app(LocalAccounts::class)->create($this->values($role));
    }
    private function values(string $role='OPERADOR'): array
    {
        return ['name'=>'Cuenta local', 'email'=>\Illuminate\Support\Str::uuid().'@example.invalid', 'role'=>$role,
            'password'=>$this->newPassword, 'password_confirmation'=>$this->newPassword];
    }
    public function test_session_login_regenerates_id_and_logout_invalidates_authentication_and_token(): void
    {
        $hash=$this->operator->password;
        $this->get('/login')->assertOk()->assertDontSee('Cerrar sesión');
        $before=session()->getId();$csrf=session()->token();
        $this->signIn($this->operator->email,$this->password);
        $this->assertNotSame($before,session()->getId());$this->assertNotSame($csrf,session()->token());
        $this->assertAuthenticatedAs($this->operator);$this->assertSame($hash,$this->operator->fresh()->password);
        $before=session()->getId();$csrf=session()->token();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();$this->assertNotSame($before,session()->getId());$this->assertNotSame($csrf,session()->token());
        $this->getJson('/')->assertUnauthorized();
    }
    public function test_basic_header_no_longer_authenticates_and_browser_visitors_get_login(): void
    {
        $this->withBasicAuth($this->operator->email,$this->password)->getJson('/')->assertUnauthorized();
        $this->get('/')->assertRedirect('/login');
    }
    public function test_disabled_credentials_and_prior_session_are_rejected(): void
    {
        $admin=$this->account();$otherAdmin=$this->account();
        $this->signIn($admin->email,$this->newPassword);
        app(LocalAccounts::class)->deactivate($admin->id);
        $this->getJson('/accounts')->assertUnauthorized();$this->assertGuest();
        $this->postJson('/login',['email'=>$admin->email,'password'=>$this->newPassword])->assertUnprocessable();
        $this->assertTrue($otherAdmin->fresh()->is_active);
    }
    public function test_only_admin_can_manage_accounts_but_cannot_read_or_operate_foreign_documents(): void
    {
        $document=$this->issue();$admin=$this->account();
        $this->signIn($this->operator->email,$this->password);
        $this->get('/accounts')->assertForbidden();$this->post('/accounts',$this->values('ADMIN'))->assertForbidden();
        $this->post('/accounts/'.$admin->id.'/deactivate')->assertForbidden();
        $this->signIn($admin->email,$this->newPassword);
        $this->get('/accounts')->assertOk();
        $this->get('/invoices/'.$document->id)->assertNotFound();
        $this->get('/invoices/'.$document->id.'/evidence/xml')->assertNotFound();
        $this->post('/invoices/'.$document->id.'/simulate',['scenario'=>'accepted'])->assertNotFound();
        $new=$this->values();$this->post('/accounts',$new)->assertStatus(303);
        $created=User::query()->where('email',$new['email'])->sole();
        $this->assertSame('OPERADOR',$created->role);$this->assertTrue(Hash::check($new['password'],$created->password));
        $this->assertNotSame($new['password'],$created->password);
        $historical=$created->getAttributes();
        $this->post('/accounts/'.$created->id.'/deactivate')->assertStatus(303);
        $this->assertSame(array_diff_key($historical,['is_active'=>true]),array_diff_key($created->fresh()->getAttributes(),['is_active'=>true]));
    }
    public function test_admin_operates_only_own_invoice_and_operator_cannot_assign_role_or_owner(): void
    {
        $admin=$this->account();$this->signIn($admin->email,$this->newPassword);
        $this->post('/invoices',$this->input())->assertStatus(303);
        $own=\App\Models\Invoice::query()->where('user_id',$admin->id)->latest()->firstOrFail();
        $this->get('/invoices/'.$own->id)->assertOk();
        $this->signIn($this->operator->email,$this->password);
        $this->postJson('/invoices',$this->input(['user_id'=>$admin->id,'role'=>'ADMIN']))->assertUnprocessable();
        $this->get('/invoices/'.$own->id)->assertNotFound();
    }
    public function test_last_active_admin_cannot_be_disabled_and_repeated_disable_is_safe(): void
    {
        $first=$this->account();$second=$this->account();
        $this->signIn($second->email,$this->newPassword);
        $this->post('/accounts/'.$first->id.'/deactivate')->assertStatus(303);
        $this->post('/accounts/'.$first->id.'/deactivate')->assertStatus(303);
        $this->post('/accounts/'.$second->id.'/deactivate')->assertConflict();
        $this->assertTrue($second->fresh()->is_active);
    }
    public function test_passwords_and_roles_are_validated_and_credentials_never_flashed(): void
    {
        $admin=$this->account();$this->signIn($admin->email,$this->newPassword);
        $this->postJson('/accounts',$this->values('SUPERADMIN'))->assertUnprocessable();
        $weak=$this->values();$weak['password']=$weak['password_confirmation']='short';
        $this->post('/accounts',$weak)->assertSessionHasErrors('password');
        $this->assertArrayNotHasKey('password',session()->getOldInput());
        $this->assertArrayNotHasKey('password_confirmation',session()->getOldInput());
        $input=$this->values();$input['email']=$this->operator->email;
        $hash=$this->operator->password;$this->postJson('/accounts',$input)->assertUnprocessable();
        $this->assertSame($hash,$this->operator->fresh()->password);
        $this->assertSame('OPERADOR',$this->operator->fresh()->role);
    }
    public function test_csrf_on_login_logout_creation_and_deactivation(): void
    {
        $admin=$this->account();$this->signIn($admin->email,$this->newPassword);
        $this->app->instance('env','local');
        $this->post('/logout')->assertStatus(419);
        $this->post('/accounts',$this->values())->assertStatus(419);
        $this->post('/accounts/'.$admin->id.'/deactivate')->assertStatus(419);
        $this->post('/login',['email'=>$admin->email,'password'=>$this->newPassword])->assertStatus(419);
        $this->get('/logout')->assertStatus(405);
    }
    public function test_rate_limits_survive_success_for_ip_and_expire_after_window(): void
    {
        for($i=0;$i<5;$i++){$this->postJson('/login',['email'=>$this->operator->email,'password'=>'wrong'])->assertUnprocessable();}
        $this->postJson('/login',['email'=>$this->operator->email,'password'=>$this->password])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->signIn($this->operator->email,$this->password);$this->post('/logout');
        for($i=0;$i<10;$i++){$this->postJson('/login',['email'=>"absent{$i}@example.invalid",'password'=>'wrong'])->assertUnprocessable();}
        $this->postJson('/login',['email'=>'other@example.invalid','password'=>'wrong'])->assertStatus(429);
    }
    public function test_environment_host_headers_and_read_only_config_remain_restricted(): void
    {
        $this->get('/login')->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Cache-Control','no-store, private');
        $this->get('http://evil.example.invalid/login')->assertStatus(400)->assertHeader('X-Content-Type-Options','nosniff');
        $this->get('http://localhost/login')->assertOk();
        $this->signIn($this->operator->email,$this->password);
        $this->get('/settings')->assertOk()->assertSee('00000000000')->assertDontSee(config('app.key'))->assertDontSee('H2_OPERATOR_PASSWORD');
        $this->post('/settings',[])->assertStatus(405);
        $this->app->instance('env','production');$this->get('/login')->assertStatus(503);
    }
    public function test_existing_case_sensitive_email_keeps_identity_and_cannot_be_duplicated(): void
    {
        $this->operator->email='Legacy.'.\Illuminate\Support\Str::uuid().'@EXAMPLE.invalid';$this->operator->save();
        $original=$this->operator->fresh()->getAttributes();
        $this->signIn(strtolower($this->operator->email),$this->password);
        $this->assertSame($original,$this->operator->fresh()->getAttributes());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(LocalAccounts::class)->create(array_replace($this->values(),['email'=>strtolower($this->operator->email)]));
    }
    public function test_database_rejects_unknown_roles(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('users')->where('id',$this->operator->id)->update(['role'=>'SUPERADMIN']);
    }
    public function test_noninteractive_bootstrap_has_no_password_argument_or_default(): void
    {
        $command=new \App\Console\Commands\BootstrapLocalAdmin();$command->setLaravel($this->app);
        $tester=new \Symfony\Component\Console\Tester\CommandTester($command);
        $this->expectException(\RuntimeException::class);
        $tester->execute(['--email'=>'unused@example.invalid','--name'=>'Admin'],['interactive'=>false]);
    }
    private function bootstrap(string $email): \Symfony\Component\Console\Tester\CommandTester
    {
        $command=new \App\Console\Commands\BootstrapLocalAdmin();
        $command->setLaravel($this->app);
        $tester=new \Symfony\Component\Console\Tester\CommandTester($command);
        $tester->setInputs([$this->newPassword,$this->newPassword]);
        $tester->execute(['--email'=>$email,'--name'=>'Admin ficticio'],['interactive'=>true]);
        $this->assertStringNotContainsString($this->newPassword,$tester->getDisplay());
        return $tester;
    }
    public function test_bootstrap_requires_new_account_and_cannot_elevate_existing_operator(): void
    {
        $hash=$this->operator->password;
        $this->assertSame(1,$this->bootstrap($this->operator->email)->getStatusCode());
        $this->assertSame('OPERADOR',$this->operator->fresh()->role);$this->assertSame($hash,$this->operator->fresh()->password);
    }
    public function test_bootstrap_creates_admin_once_without_default_or_echoed_password(): void
    {
        $input=$this->values('ADMIN');
        $this->assertSame(0,$this->bootstrap($input['email'])->getStatusCode());
        $this->assertSame('ADMIN',User::query()->where('email',$input['email'])->sole()->role);
        $this->assertSame(1,$this->bootstrap($this->values('ADMIN')['email'])->getStatusCode());
    }
}
