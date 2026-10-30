<?php
namespace App\Http\Controllers;

use App\Accounts\LocalAccounts;
use App\Models\User;
use Illuminate\Http\Request;

final class AccountController
{
    public function index() { return view('accounts.index', ['users'=>User::query()->orderBy('id')->simplePaginate(20)]); }
    public function store(Request $request, LocalAccounts $accounts)
    {
        $created = $accounts->create($request->only(['name','email','role','password','password_confirmation']));
        \Illuminate\Support\Facades\Log::notice('local_account_created', ['actor'=>$request->user()->id, 'target'=>$created->id, 'role'=>$created->role]);
        return redirect()->route('accounts.index', status:303);
    }
    public function deactivate(Request $request, string $id, LocalAccounts $accounts)
    {
        abort_unless(ctype_digit($id),404);
        $accounts->deactivate((int)$id);
        \Illuminate\Support\Facades\Log::notice('local_account_deactivated', ['actor'=>$request->user()->id, 'target'=>(int)$id]);
        return redirect()->route('accounts.index', status:303);
    }
}
