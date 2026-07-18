<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'max' => [
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'confirmed' => 'La confirmación del campo :attribute no coincide.',
    'unique' => 'El valor del campo :attribute ya está registrado.',
    'in' => 'El valor seleccionado para :attribute no es válido.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'password' => [
        'letters' => 'La :attribute debe contener al menos una letra.',
        'mixed' => 'La :attribute debe contener al menos una letra mayúscula y una minúscula.',
        'numbers' => 'La :attribute debe contener al menos un número.',
        'symbols' => 'La :attribute debe contener al menos un símbolo.',
    ],
    'attributes' => [
        'document_type' => 'tipo de documento',
        'document_number' => 'número de documento',
        'first_name' => 'nombres',
        'last_name' => 'apellidos',
        'email' => 'correo electrónico',
        'phone' => 'teléfono',
        'password' => 'contraseña',
    ],
];
