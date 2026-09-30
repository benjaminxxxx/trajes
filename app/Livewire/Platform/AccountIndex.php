<?php

namespace App\Livewire\Platform;

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Sale;
use App\Services\Core\AccountService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Panel de la plataforma: todos los negocios que usan el sistema, su dueño,
 * su estado y cuánto lo usan. Desde aquí se registra el negocio de un
 * cliente, se cambia su dueño o se le bloquea el acceso.
 *
 * Solo lee datos agregados de cada negocio (conteos), nunca su detalle.
 */
#[Title('Plataforma')]
class AccountIndex extends Component
{
    #[Url(history: true)]
    public string $search = '';

    #[Url(as: 'estado', history: true)]
    public string $status = 'todos';

    public ?int $accountId = null;

    public bool $showForm = false;

    public bool $showOwner = false;

    public bool $showBlock = false;

    // Negocio
    public string $name = '';

    public ?string $ruc = null;

    public ?string $address = null;

    public ?string $phone = null;

    // Dueño
    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $blockReason = '';

    public function create(): void
    {
        $this->resetForms();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForms();
        $account = Account::findOrFail($id);

        $this->accountId = $account->id;
        $this->name = $account->name;
        $this->ruc = $account->ruc;
        $this->address = $account->address;
        $this->phone = $account->phone;
        $this->showForm = true;
    }

    public function save(AccountService $service): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'ruc' => ['nullable', 'digits:11'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        if (! $this->accountId) {
            $rules['ownerName'] = ['required', 'string', 'max:255'];
            $rules['ownerEmail'] = ['required', 'email', 'max:255'];
        }

        $this->validate($rules, [], ['ownerName' => 'nombre del dueño', 'ownerEmail' => 'correo del dueño']);

        $data = ['name' => trim($this->name), 'ruc' => $this->ruc ?: null, 'address' => $this->address ?: null, 'phone' => $this->phone ?: null];

        if ($this->accountId) {
            Account::findOrFail($this->accountId)->update($data);
            Flux::toast(variant: 'success', text: 'Datos del negocio actualizados.');
        } else {
            $service->createAccountForClient($data, $this->ownerName, $this->ownerEmail);
            Flux::toast(variant: 'success', text: "«{$data['name']}» registrado. Su dueño puede entrar con Google o con el enlace que le llegó al correo.");
        }

        $this->showForm = false;
    }

    public function openOwner(int $id): void
    {
        $this->resetForms();
        $this->accountId = $id;
        $this->showOwner = true;
    }

    public function changeOwner(AccountService $service): void
    {
        $this->validate([
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerEmail' => ['required', 'email', 'max:255'],
        ], [], ['ownerName' => 'nombre', 'ownerEmail' => 'correo']);

        $account = Account::findOrFail($this->accountId);
        $owner = $service->changeOwner($account, $this->ownerName, $this->ownerEmail, Auth::user());

        $this->showOwner = false;
        Flux::toast(variant: 'success', text: "{$owner->email} ahora es el dueño de «{$account->name}».");
    }

    public function openBlock(int $id): void
    {
        $this->resetForms();
        $this->accountId = $id;
        $this->showBlock = true;
    }

    public function block(AccountService $service): void
    {
        $this->validate(['blockReason' => ['required', 'string', 'max:255']], [], ['blockReason' => 'motivo']);

        $account = Account::findOrFail($this->accountId);
        $service->block($account, $this->blockReason);

        $this->showBlock = false;
        Flux::toast(variant: 'success', text: "«{$account->name}» bloqueado: nadie de ese negocio puede entrar.");
    }

    public function unblock(int $id, AccountService $service): void
    {
        $account = Account::findOrFail($id);
        $service->unblock($account);

        Flux::toast(variant: 'success', text: "«{$account->name}» vuelve a estar activo.");
    }

    protected function resetForms(): void
    {
        $this->resetValidation();
        $this->reset(['accountId', 'name', 'ruc', 'address', 'phone', 'ownerName', 'ownerEmail', 'blockReason']);
    }

    /**
     * Conteo por negocio de una tabla del negocio (sin el filtro por negocio).
     *
     * @param  class-string  $model
     * @return Collection<int, int>
     */
    protected function countsByAccount(string $model): Collection
    {
        return $model::withoutAccountScope()
            ->selectRaw('account_id, COUNT(*) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');
    }

    public function render(): View
    {
        $accounts = Account::query()
            ->with('owner:id,name,email')
            ->withCount(['memberships as members_count' => fn ($query) => $query->where('status', AccountMember::STATUS_ACTIVE)])
            ->when(filled($this->search), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhere('ruc', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('owner', fn ($owner) => $owner->where('email', 'like', '%'.trim($this->search).'%'))))
            ->when($this->status !== 'todos', fn ($query) => $query->where('status', $this->status))
            ->latest('id')
            ->get();

        return view('livewire.platform.account-index', [
            'accounts' => $accounts,
            'statusCounts' => Account::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'usage' => [
                'products' => $this->countsByAccount(Product::class),
                'sales' => $this->countsByAccount(Sale::class),
                'rentals' => $this->countsByAccount(Rental::class),
            ],
            'selected' => $this->accountId ? Account::find($this->accountId) : null,
            'statuses' => Account::STATUS_LABELS,
        ]);
    }
}
