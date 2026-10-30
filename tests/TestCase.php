<?php

namespace Tests;

use App\Invoices\InvoiceIssuer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

abstract class TestCase extends LaravelTestCase
{
    protected User $operator;
    protected string $password = 'H2-ONLY-TEST-PASSWORD';

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.connections.pgsql.database') !== 'emisor_h2_test' || !app()->environment('testing')) {
            throw new RuntimeException('Las pruebas no pueden escribir fuera de emisor_h2_test.');
        }
        if (!Schema::hasTable('invoices')) {
            throw new RuntimeException('Ejecutar h2:setup --testing antes de las pruebas.');
        }
        $this->operator = $this->newOperator();
    }

    protected function newOperator(): User
    {
        return User::query()->create([
            'name' => 'Operador ficticio', 'email' => Str::uuid().'@example.invalid',
            'password' => Hash::make($this->password),
        ]);
    }

    protected function signIn(string $email, string $password): static
    {
        $this->get('/login')->assertOk();
        $this->post('/login', ['email'=>$email, 'password'=>$password, '_token'=>session()->token()])->assertRedirect('/');
        return $this;
    }

    protected function input(array $overrides = []): array
    {
        return $overrides + [
            'idempotency_key' => (string) Str::uuid(), 'description' => 'PRODUCTO FICTICIO H2',
            'quantity' => '1', 'unit_price' => '100.00',
        ];
    }

    protected function issue(array $overrides = []): Invoice
    {
        return app(InvoiceIssuer::class)->issue($this->operator->id, $this->input($overrides));
    }
}
