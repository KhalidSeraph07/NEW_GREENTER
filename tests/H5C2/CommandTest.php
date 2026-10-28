<?php
namespace Tests\H5C2;
use Illuminate\Support\Facades\Artisan;
final class CommandTest extends CaseBase
{
    public function test_operator_cli_creates_only_new_fixture_and_preserves_historical_and_effective_identity(): void
    {
        $this->assertSame(0,Artisan::call('h5c2:process',['action'=>'fixture','family'=>'invoice','--user'=>(string)$this->operator->id,'--input'=>json_encode($this->input())]));
        $root=json_decode(Artisan::output(),true,flags:JSON_THROW_ON_ERROR);$this->assertTrue($root['simulated']);$this->assertFalse($root['official_cdr']);
        $this->assertSame(0,Artisan::call('h5c2:process',['action'=>'send','family'=>'invoice','id'=>$root['id'],'--scenario'=>'rejected_c2_zip']));
        $r=\App\Models\Invoice::query()->findOrFail($root['id']);
        $this->assertSame(0,Artisan::call('h5c2:process',['action'=>'prepare','family'=>'invoice','--user'=>(string)$this->operator->id,'--input'=>json_encode($this->reissueInput($r))]));
        $child=json_decode(Artisan::output(),true,flags:JSON_THROW_ON_ERROR);$this->assertNotSame($root['id'],$child['id']);
        $this->assertSame(0,Artisan::call('h5c2:process',['action'=>'send','family'=>'invoice','id'=>$child['id']]));
        $this->assertSame(0,Artisan::call('h5c2:process',['action'=>'status','family'=>'invoice','id'=>$root['id']]));
        $result=json_decode(Artisan::output(),true,flags:JSON_THROW_ON_ERROR);
        $this->assertSame('simulated_rejected',$result['historical_state']);$this->assertSame('simulated_accepted',$result['effective_state']);$this->assertSame($child['id'],$result['effective_id']);
        file_put_contents(storage_path('audit/h5c2/cli-'.$root['id'].'.json'),json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
    public function test_guard_disables_correction_when_environment_is_not_fictitious(): void
    {
        config(['h2.mode'=>'production']);$this->expectException(\LogicException::class);\App\Corrections\Family::guard();
    }
}
