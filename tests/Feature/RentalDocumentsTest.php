<?php

use App\Livewire\Rental\RentalDetailModal;
use App\Livewire\Rental\RentalForm;
use App\Models\Rental;
use App\Models\RentalMedia;
use App\Models\User;
use App\Services\Rental\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Formulario de alquiler con cliente y una prenda física lista para guardar.
 */
function rentalFormReadyToSave(User $user)
{
    $variant = createProductIn(null);
    $unit = createPhysicalUnit($variant);

    return Livewire::actingAs($user)
        ->test(RentalForm::class)
        ->set('client_id', createClient()->id)
        ->set('items', ['line-1' => [
            'product_id' => $variant->product_id, 'product_name' => 'Terno', 'product_code' => 'T-1',
            'category_id' => null, 'size_id' => null, 'color_id' => null,
            'physical_product_id' => $unit->id, 'physical_code' => $unit->code,
            'quantity' => 1, 'unit_price' => 80, 'discount_percent' => 0,
        ]]);
}

test('a rental requires at least one photo of the client document', function () {
    rentalFormReadyToSave(createSeller())
        ->call('save')
        ->assertHasErrors('documentPhotos');

    expect(Rental::count())->toBe(0);
});

test('document photos are stored privately, reduced and labelled with the rental', function () {
    rentalFormReadyToSave(createSeller())
        ->set('photoUploads', [UploadedFile::fake()->image('frente.jpg', 3000, 2000)])
        ->set('photoUploads', [UploadedFile::fake()->image('reverso.jpg', 1200, 800)])
        ->assertSet('documentLabels', ['Anverso', 'Reverso'])
        ->set('documentLabels.1', 'Reverso con sello')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('documentPhotos', []);

    $rental = Rental::firstOrFail();
    $documents = $rental->documents;

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('label')->all())->toBe(['Anverso', 'Reverso con sello'])
        ->and($documents->first()->file_path)->toStartWith("rentals/{$rental->id}/dni-");

    Storage::disk('local')->assertExists($documents->first()->file_path);
    Storage::disk('public')->assertMissing($documents->first()->file_path);

    [$width] = getimagesizefromstring(Storage::disk('local')->get($documents->first()->file_path));
    expect($width)->toBe(1600);
});

test('document photos are only served to users who can see rentals', function () {
    $media = RentalMedia::create([
        'rental_id' => rentAllGarments(),
        'file_path' => 'rentals/1/dni-test.jpg',
        'type' => 'dni',
    ]);
    Storage::disk('local')->put($media->file_path, UploadedFile::fake()->image('dni.jpg')->getContent());

    auth()->logout();

    $this->get($media->url())->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($media->url())->assertForbidden();
    $this->actingAs(createSeller())->get($media->url())
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
});

test('more photos can be added later and only editors can delete them', function () {
    $rentalId = rentAllGarments();

    Livewire::actingAs(createSeller())
        ->test(RentalDetailModal::class)
        ->call('loadRental', $rentalId)
        ->set('photoUploads', [UploadedFile::fake()->image('sello.jpg')])
        ->call('saveDocuments')
        ->assertHasNoErrors();

    $media = RentalMedia::firstOrFail();

    Livewire::actingAs(createSeller())
        ->test(RentalDetailModal::class)
        ->call('loadRental', $rentalId)
        ->call('deleteDocument', $media->id)
        ->assertForbidden();

    Livewire::actingAs(createAdmin())
        ->test(RentalDetailModal::class)
        ->call('loadRental', $rentalId)
        ->call('deleteDocument', $media->id);

    expect(RentalMedia::count())->toBe(0);
    Storage::disk('local')->assertMissing($media->file_path);
});

test('redoing a cancelled rental reuses its document photos', function () {
    $admin = createAdmin();

    rentalFormReadyToSave($admin)
        ->set('photoUploads', [UploadedFile::fake()->image('frente.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $cancelled = Rental::firstOrFail();
    $this->actingAs($admin);
    app(RentalService::class)->cancel($cancelled, 'Prenda equivocada');

    $form = Livewire::actingAs($admin)
        ->withQueryParams(['from' => $cancelled->id])
        ->test(RentalForm::class)
        ->assertSet('reusableDocuments', 1);

    // La prenda original volvió a estar disponible y se precargó; se guarda sin tomar fotos nuevas.
    $form->call('save')->assertHasNoErrors();

    $redone = Rental::whereKeyNot($cancelled->id)->firstOrFail();

    expect($redone->documents)->toHaveCount(1)
        ->and($redone->documents->first()->file_path)->not->toBe($cancelled->documents->first()->file_path);

    Storage::disk('local')->assertExists($redone->documents->first()->file_path);
});

/**
 * Alquiler en curso con una prenda, registrado por el servicio. Devuelve su id.
 */
function rentAllGarments(): int
{
    test()->actingAs(createAdmin());
    $variant = createProductIn(null);
    $unit = createPhysicalUnit($variant);

    return app(RentalService::class)->register(
        ['client_id' => createClient()->id, 'pickup_date' => now(), 'expected_return_date' => now()->addDay()],
        [['product_id' => $variant->product_id, 'physical_product_id' => $unit->id, 'quantity' => 1, 'unit_price' => 80]],
    )->id;
}
