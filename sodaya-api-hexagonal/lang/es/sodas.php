<?php

declare(strict_types=1);

return [

    'closure_already_exists' => 'La soda ya tiene un cierre registrado para esa fecha.',
    'closure_date_in_past' => 'La fecha no puede ser anterior a hoy.',
    'closure_date_invalid' => 'La fecha del cierre debe tener el formato AAAA-MM-DD.',
    'closure_not_found' => 'El cierre no existe.',
    'closure_reason_invalid' => 'El motivo del cierre no puede estar vacío ni superar los :max caracteres.',
    'day_of_week_out_of_range' => 'El día debe estar entre :min (lunes) y :max (domingo).',
    'name_invalid' => 'El nombre de la soda es obligatorio y no puede superar los :max caracteres.',
    'owner_password_prompt' => 'Contraseña del dueño',
    'payment_account_id_invalid' => 'El identificador de la cuenta de pago no puede estar vacío ni superar los :max caracteres.',
    'register_soda_description' => 'Registra una soda y la cuenta de su dueño.',
    'soda_registered' => 'Soda registrada: :name (:id). Dueño: :email.',
    'time_of_day_invalid' => 'La hora debe tener el formato HH:MM, entre 00:00 y 23:59.',
    'time_slot_inverted' => 'La hora de apertura debe ser anterior a la de cierre.',
    'time_slot_not_found' => 'La franja no existe.',
    'time_slot_overlaps' => 'La franja se traslapa con otra del mismo día.',

];
