<?php
declare(strict_types=1);

return [
    'app_name' => 'Mesa de ayuda · Unidad Virtual',
    'base_url' => 'https://uvirtual.uninunez.edu.co/mesa-ayuda-uv', // Sin barra final.
    'db' => [
        'host' => 'localhost',
        'name' => 'uvirtual_mesaayuda_uv',
        'user' => 'uvirtual_mesaayuda',
        'pass' => 'Mesaayuda.2026**.',
    ],
    // Usa una frase aleatoria larga, única y privada. Sirve para la instalación inicial.
    'install_key' => 'MesaUV2026-Unidad-Virtual',
    'allow_local_signup' => true, // Desactivar después de configurar Google, si procede.
    'google' => [
        'client_id' => '',
        'client_secret' => '',
        // Déjalo vacío para permitir cuentas Google de estudiantes externos.
        'allowed_domain' => '',
    ],
    'upload_max_bytes' => 5242880,
    // Metas iniciales de prueba, en horas corridas. Ajustar con la política de servicio aprobada.
    'sla_hours' => [
        'P1' => ['first' => 2, 'resolution' => 8],
        'P2' => ['first' => 4, 'resolution' => 24],
        'P3' => ['first' => 8, 'resolution' => 72],
        'P4' => ['first' => 24, 'resolution' => 120],
    ],
];
