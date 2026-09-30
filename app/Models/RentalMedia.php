<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto adjunta a un alquiler (DNI del cliente, comprobante de garantía,
 * estado de la prenda). Se guarda en el disco PRIVADO y solo se sirve por la
 * ruta `rental.media.show`, que exige permiso para ver alquileres.
 */
class RentalMedia extends Model
{
    use BelongsToAccount, HasFactory;

    public const DISK = 'local';

    public const TYPES = [
        'dni' => 'Documento de identidad',
        'guarantee_receipt' => 'Comprobante de garantía',
        'delivery_condition' => 'Estado al entregar',
        'return_damage' => 'Daño al devolver',
    ];

    /**
     * Etiquetas sugeridas para las fotos del documento, en el orden habitual.
     */
    public const DNI_LABELS = ['Anverso', 'Reverso', 'Sello', 'Otro'];

    protected $table = 'rental_media';

    protected $fillable = [
        'rental_id',
        'user_id',
        'file_path',
        'type',
        'label',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return route('rental.media.show', $this);
    }
}
