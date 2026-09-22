<?php

use App\Enums\WmsRole;
use App\Jobs\ProcessLowStockAlert;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->admin = User::factory()->wmsAdmin()->create();
    $this->operator = User::factory()->wmsOperator()->create();
    $this->noRole = User::factory()->create(['wms_role' => null]);
});

function bearer(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}

it('issues a bearer token on login', function () {
    $user = User::factory()->wmsAdmin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'admin@example.com')
        ->assertJsonPath('user.wms_role', WmsRole::Admin->value)
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email', 'wms_role']]);
});

it('rejects invalid login credentials with 401 envelope', function () {
    User::factory()->wmsAdmin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong',
    ])->assertUnauthorized()
        ->assertJsonPath('success', false);
});

it('forbids users without a wms role on protected routes', function () {
    $this->getJson('/api/products', bearer($this->noRole))
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('allows admin to create product and receive stock', function () {
    $warehouse = Warehouse::factory()->create();
    $location = Location::factory()->create(['warehouse_id' => $warehouse->id]);

    $productResponse = $this->postJson('/api/products', [
        'sku' => 'SKU-100',
        'name' => 'Widget',
        'unit' => 'ea',
    ], bearer($this->admin))->assertCreated();

    $productId = $productResponse->json('data.id') ?? $productResponse->json('id');

    $this->postJson('/api/inventory/receive', [
        'product_id' => $productId,
        'location_id' => $location->id,
        'quantity' => 25,
        'reference' => 'RCV-1',
    ], bearer($this->admin))
        ->assertOk()
        ->assertJsonPath('data.quantity', '25.0000')
        ->assertJsonPath('reference_number', 'RCV-1');

    expect(Inventory::query()->where('product_id', $productId)->value('quantity'))->toBe('25.0000');
    expect(StockMovement::query()->where('reference_number', 'RCV-1')->count())->toBe(1);
});

it('forbids warehouse operator from creating warehouses', function () {
    $this->postJson('/api/warehouses', [
        'code' => 'WH-OP',
        'name' => 'Operator Warehouse',
    ], bearer($this->operator))->assertForbidden();
});

it('allows admin warehouse and location crud and operator product crud', function () {
    $warehouse = $this->postJson('/api/warehouses', [
        'code' => 'WH-A',
        'name' => 'Alpha',
    ], bearer($this->admin))->assertCreated()->json();

    $warehouseId = $warehouse['data']['id'] ?? $warehouse['id'];

    $location = $this->postJson("/api/warehouses/{$warehouseId}/locations", [
        'code' => 'A-1',
        'name' => 'Aisle 1',
    ], bearer($this->admin))->assertCreated()->json();

    $locationId = $location['data']['id'] ?? $location['id'];

    $this->postJson('/api/products', [
        'sku' => 'SKU-OP',
        'name' => 'Operator Product',
        'unit' => 'ea',
    ], bearer($this->operator))->assertCreated();

    expect(Location::query()->find($locationId))->not->toBeNull();
});

it('transfers stock with two movement rows sharing a reference', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $from = Location::factory()->create(['warehouse_id' => $warehouse->id, 'code' => 'FROM']);
    $to = Location::factory()->create(['warehouse_id' => $warehouse->id, 'code' => 'TO']);

    Inventory::factory()->create([
        'product_id' => $product->id,
        'location_id' => $from->id,
        'quantity' => 50,
    ]);

    $this->postJson('/api/inventory/transfer', [
        'product_id' => $product->id,
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'quantity' => 20,
        'reference' => 'TR-9',
    ], bearer($this->operator))
        ->assertOk()
        ->assertJsonPath('reference_number', 'TR-9');

    expect(Inventory::query()->where('location_id', $from->id)->value('quantity'))->toBe('30.0000');
    expect(Inventory::query()->where('location_id', $to->id)->value('quantity'))->toBe('20.0000');
    expect(StockMovement::query()->where('reference_number', 'TR-9')->count())->toBe(2);
});

it('rejects dispatch that would go negative with 409', function () {
    $product = Product::factory()->create();
    $location = Location::factory()->create();
    Inventory::factory()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 5,
    ]);

    $this->postJson('/api/inventory/dispatch', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 10,
    ], bearer($this->admin))
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    expect(Inventory::query()->where('location_id', $location->id)->value('quantity'))->toBe('5.0000');
});

it('rejects stock ops on inactive products', function () {
    $product = Product::factory()->inactive()->create();
    $location = Location::factory()->create();

    $this->postJson('/api/inventory/receive', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 1,
    ], bearer($this->admin))->assertStatus(409);
});

it('filters stock movements by warehouse on either location', function () {
    $product = Product::factory()->create();
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $locA = Location::factory()->create(['warehouse_id' => $warehouseA->id]);
    $locB = Location::factory()->create(['warehouse_id' => $warehouseB->id]);

    Inventory::factory()->create([
        'product_id' => $product->id,
        'location_id' => $locA->id,
        'quantity' => 10,
    ]);

    $this->postJson('/api/inventory/receive', [
        'product_id' => $product->id,
        'location_id' => $locB->id,
        'quantity' => 3,
        'reference' => 'RCV-B',
    ], bearer($this->admin))->assertOk();

    $this->getJson('/api/stock-movements?warehouse_id='.$warehouseB->id, bearer($this->admin))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_number', 'RCV-B');
});

it('enqueues a low stock alert when total falls below threshold', function () {
    Queue::fake();

    $product = Product::factory()->withLowStockThreshold(10)->create();
    $location = Location::factory()->create();

    Inventory::factory()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 12,
    ]);

    $this->postJson('/api/inventory/dispatch', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => 5,
    ], bearer($this->admin))->assertOk();

    Queue::assertPushed(ProcessLowStockAlert::class, fn (ProcessLowStockAlert $job) => $job->productId === $product->id);

    (new ProcessLowStockAlert($product->id))->handle();

    expect(LowStockAlert::query()->where('product_id', $product->id)->exists())->toBeTrue();
});

it('enforces unique product sku', function () {
    Product::factory()->create(['sku' => 'DUP-1']);

    $this->postJson('/api/products', [
        'sku' => 'DUP-1',
        'name' => 'Dup',
        'unit' => 'ea',
    ], bearer($this->admin))
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});
