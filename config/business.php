<?php

/*
|--------------------------------------------------------------------------
| Datos del negocio
|--------------------------------------------------------------------------
|
| Se muestran en los comprobantes (PDF) de venta y alquiler. Se configuran
| en el .env para no tocar código al cambiar de local o de teléfono.
|
*/

return [
    'name' => env('BUSINESS_NAME', 'Kipusoft Alquiler'),
    'ruc' => env('BUSINESS_RUC'),
    'address' => env('BUSINESS_ADDRESS'),
    'phone' => env('BUSINESS_PHONE'),

    // Días que dura el enlace público del comprobante enviado por WhatsApp.
    'receipt_link_days' => (int) env('BUSINESS_RECEIPT_LINK_DAYS', 30),

    'receipt_footer' => env('BUSINESS_RECEIPT_FOOTER', 'Gracias por su preferencia.'),

    // Peso máximo por archivo en la galería de productos (MB). Las fotos se
    // reducen al guardarse; los videos se guardan tal cual. El servidor debe
    // aceptar al menos el mayor de los dos (upload_max_filesize y post_max_size).
    'media' => [
        'max_photo_mb' => (int) env('MEDIA_MAX_PHOTO_MB', 12),
        'max_video_mb' => (int) env('MEDIA_MAX_VIDEO_MB', 150),
    ],
];
