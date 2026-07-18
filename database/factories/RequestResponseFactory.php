<?php

namespace Database\Factories;

use App\Models\ProcedureRequest;
use App\Models\RequestResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestResponse> */
class RequestResponseFactory extends Factory
{
    public function definition(): array
    {
        $storedName = fake()->uuid().'.pdf';

        return [
            'procedure_request_id' => ProcedureRequest::factory(),
            'user_id' => User::factory(),
            'summary' => 'Respuesta final ficticia emitida para la solicitud.',
            'responded_at' => now(),
            'disk' => 'private',
            'path' => 'responses/'.$storedName,
            'stored_name' => $storedName,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', fake()->uuid()),
        ];
    }
}
