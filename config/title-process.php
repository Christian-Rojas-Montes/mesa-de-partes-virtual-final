<?php

return [
    'professional_exam' => [
        'minimum_experience_months' => (int) env('TITLE_EXAM_MIN_EXPERIENCE_MONTHS', 6),
        'theory_weight' => (float) env('TITLE_EXAM_THEORY_WEIGHT', 30),
        'practical_weight' => (float) env('TITLE_EXAM_PRACTICAL_WEIGHT', 70),
        'passing_grade' => (float) env('TITLE_EXAM_PASSING_GRADE', 13),
        'maximum_opportunities' => (int) env('TITLE_EXAM_MAX_OPPORTUNITIES', 3),
        'requirements' => [
            ['code' => 'schedule_request', 'label' => 'Solicitud dirigida al director general solicitando fecha'],
            ['code' => 'identity', 'label' => 'DNI', 'sensitive' => true],
            ['code' => 'graduate_certificate', 'label' => 'Constancia de egresado'],
            ['code' => 'experience', 'label' => 'Certificado o constancia de experiencia profesional o EFSRT'],
            ['code' => 'title_minutes', 'label' => 'Actas de titulación', 'physical' => true, 'quantity' => 3, 'sensitive' => true],
            ['code' => 'payment', 'label' => 'Recibo de pago'],
            ['code' => 'language', 'label' => 'Constancia de idioma extranjero o lengua originaria'],
            ['code' => 'authenticity', 'label' => 'Declaración jurada de autenticidad'],
        ],
    ],
    'final_dossier' => [
        'requirements' => [
            ['code' => 'registration_request', 'label' => 'Solicitud de registro del título'],
            ['code' => 'study_certificates', 'label' => 'Certificados originales de estudios de seis semestres', 'physical' => true, 'original' => true, 'quantity' => 6],
            ['code' => 'library_clearance', 'label' => 'Constancia de no adeudo a biblioteca'],
            ['code' => 'program_clearance', 'label' => 'Constancia de no adeudo al programa'],
            ['code' => 'financial_clearance', 'label' => 'Constancia de no adeudo económico'],
            ['code' => 'title_format_payment', 'label' => 'Pago por formato de título'],
            ['code' => 'title_process_payment', 'label' => 'Pago por trámite de titulación'],
            ['code' => 'printing_payment', 'label' => 'Pago por impresión'],
            ['code' => 'identity_copies', 'label' => 'Copias autenticadas del DNI', 'physical' => true, 'quantity' => 3, 'sensitive' => true],
            ['code' => 'photos', 'label' => 'Fotografías tamaño pasaporte', 'physical' => true, 'quantity' => 5, 'sensitive' => true],
            ['code' => 'title_minutes', 'label' => 'Actas de titulación', 'sensitive' => true],
            ['code' => 'practice_efsrt', 'label' => 'Constancia de prácticas o EFSRT'],
            ['code' => 'birth_certificate', 'label' => 'Partida de nacimiento original', 'physical' => true, 'original' => true, 'sensitive' => true],
            ['code' => 'title_folder', 'label' => 'Folder de titulación', 'physical' => true],
            ['code' => 'graduate_certificate', 'label' => 'Constancia de egresado'],
            ['code' => 'language', 'label' => 'Constancia de idioma'],
            ['code' => 'repository_inclusion', 'label' => 'Constancia de inclusión del trabajo en repositorio', 'conditional' => true],
        ],
    ],
    'application_work' => [
        'max_members' => (int) env('TITLE_APPLICATION_WORK_MAX_MEMBERS', 4),
        'execution_months_min' => (int) env('TITLE_APPLICATION_WORK_MONTHS_MIN', 3),
        'execution_months_max' => (int) env('TITLE_APPLICATION_WORK_MONTHS_MAX', 6),
        'similarity_max_percent' => (float) env('TITLE_APPLICATION_WORK_SIMILARITY_MAX', 25),
        'passing_grade' => (float) env('TITLE_APPLICATION_WORK_PASSING_GRADE', 13),
        'requirements' => [
            'graduate_certificate' => [
                ['code' => 'request', 'label' => 'Solicitud'], ['code' => 'practice_efsrt', 'label' => 'Constancia de prácticas o EFSRT'],
                ['code' => 'grade_report', 'label' => 'Reporte de notas con condición de egresado e invicto'], ['code' => 'identity', 'label' => 'DNI'],
                ['code' => 'language', 'label' => 'Constancia de idioma'], ['code' => 'payment', 'label' => 'Comprobante de pago'],
            ],
            'defense_file' => [
                ['code' => 'schedule_request', 'label' => 'Solicitud de fecha y hora'], ['code' => 'identity', 'label' => 'DNI'],
                ['code' => 'graduate_certificate', 'label' => 'Constancia de egresado'], ['code' => 'approval_resolution', 'label' => 'Resolución de aprobación y asesor'],
                ['code' => 'title_minutes', 'label' => 'Tres actas de titulación', 'physical' => true, 'quantity' => 3], ['code' => 'defense_payment', 'label' => 'Recibo de sustentación'],
                ['code' => 'physical_copies', 'label' => 'Ejemplares físicos del trabajo', 'physical' => true, 'quantity' => 3], ['code' => 'digital_work', 'label' => 'Versión digital'],
                ['code' => 'originality', 'label' => 'Constancia antiplagio'], ['code' => 'authenticity', 'label' => 'Declaración jurada de autenticidad'],
            ],
        ],
    ],
];
