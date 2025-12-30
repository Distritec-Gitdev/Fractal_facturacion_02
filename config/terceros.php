<?php

return [
    'api' => [
        'url' => 'https://distritec.yeminus.com/TablasSistema.Api/api/tablassistema/terceros/obtenerfiltradas',
        'empresa' => '02',
        'usuario' => 'API',
        'token_api' => [
            'url' => 'https://distritec.yeminus.com/Security.Api/token',
            'username' => 'API',
            'password' => 'Api2025*' // ¡Aquí está la contraseña del proyecto funcional!
        ]
    ],
    'cache' => [
        'prefix' => 'terceros_',
        'ttl' => 3600 // tiempo en segundos
    ]
]; 