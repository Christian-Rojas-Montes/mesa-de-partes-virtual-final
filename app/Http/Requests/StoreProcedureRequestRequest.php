<?php

namespace App\Http\Requests;

use App\Models\ProcedureRequest;
use App\Services\DynamicProcedureFormService;
use App\Services\ProcedurePrerequisiteValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Throwable;

class StoreProcedureRequestRequest extends FormRequest
{
    private const MIME_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function authorize(): bool
    {
        return $this->user()?->can('create', ProcedureRequest::class) === true;
    }

    public function rules(): array
    {
        $rules = [
            'procedure_type_id' => ['required', 'integer'], 'procedure_variant_id' => ['nullable', 'integer'],
            'eligibility' => ['nullable', 'array'], 'eligibility.*' => ['nullable', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:200'], 'description' => ['required', 'string', 'max:5000'],
            'responses' => ['nullable', 'array'], 'documents' => ['nullable', 'array', 'max:'.DynamicProcedureFormService::MAX_FILES],
            'documents.*' => ['nullable', File::types(array_values(self::MIME_EXTENSIONS))->max(DynamicProcedureFormService::MAX_FILE_KB)],
            'confirmation' => ['accepted'],
        ];

        try {
            [$service, $type, $variant] = $this->context();
            foreach ($service->fields($type, $variant, (array) $this->input('responses', [])) as $field) {
                $rules['responses.'.$field->key] = $service->fieldRules($field);
                if ($field->type->value === 'multiselect') {
                    $rules['responses.'.$field->key.'.*'] = ['string', 'max:200'];
                }
            }
        } catch (Throwable) {
            // Los errores de disponibilidad y variante se agregan después con mensajes controlados.
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            try {
                [$service, $type, $variant] = $this->context();
            } catch (Throwable $exception) {
                $message = $exception instanceof ValidationException ? collect($exception->errors())->flatten()->first() : null;
                $validator->errors()->add('procedure_type_id', $message ?: 'El trámite seleccionado no está disponible.');

                return;
            }

            $responses = (array) $this->input('responses', []);
            $requirements = $service->requirements($type, $variant, $responses);
            foreach (app(ProcedurePrerequisiteValidator::class)->errors($this->user(), $type, $responses) as $message) {
                $validator->errors()->add('prerequisites', $message);
            }
            $files = $this->file('documents', []);
            $allowedIds = $requirements->pluck('id')->map(fn ($id) => (string) $id)->all();

            foreach ($files as $requirementId => $file) {
                if ($requirementId !== 'general' && ! in_array((string) $requirementId, $allowedIds, true)) {
                    $validator->errors()->add('documents', 'Uno de los archivos no corresponde a un requisito aplicable.');

                    continue;
                }
                if (! $file instanceof UploadedFile || $requirementId === 'general') {
                    continue;
                }
                $requirement = $requirements->firstWhere('id', (int) $requirementId);
                if (! $requirement?->requires_digital_file) {
                    $validator->errors()->add("documents.{$requirementId}", 'Este requisito es exclusivamente físico y no admite archivo digital.');

                    continue;
                }
                $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                $allowed = $requirement->allowed_formats ?: array_keys(self::MIME_EXTENSIONS);
                $allowedMimes = collect($allowed)->map(fn ($value) => array_search(strtolower((string) $value), self::MIME_EXTENSIONS, true) ?: strtolower((string) $value))->all();
                if (! in_array($mime, $allowedMimes, true)) {
                    $validator->errors()->add('documents', 'Uno de los archivos no tiene un formato permitido.');
                    $validator->errors()->add("documents.{$requirementId}", "El formato del archivo de {$requirement->name} no está permitido.");
                }
                $max = min($requirement->max_file_size_kb ?: DynamicProcedureFormService::MAX_FILE_KB, DynamicProcedureFormService::MAX_FILE_KB);
                if ($file->getSize() > $max * 1024) {
                    $validator->errors()->add("documents.{$requirementId}", "El archivo de {$requirement->name} supera el tamaño permitido.");
                }
            }

            foreach ($requirements->where('required', true)->where('requires_digital_file', true) as $requirement) {
                if (! isset($files[$requirement->id])) {
                    $validator->errors()->add("documents.{$requirement->id}", "Debes adjuntar el requisito obligatorio: {$requirement->name}.");
                }
            }
            if ($requirements->isEmpty() && ! isset($files['general'])) {
                $validator->errors()->add('documents.general', 'Debes adjuntar un documento general.');
            }
        });
    }

    public function submissionContext(): array
    {
        [$service, $type, $variant] = $this->context();
        $submittedResponses = (array) $this->validated('responses', []);
        $fields = $service->fields($type, $variant, $submittedResponses);
        $responses = collect($submittedResponses)->only($fields->pluck('key'))->all();
        $requirements = $service->requirements($type, $variant, $responses);

        return compact('service', 'type', 'variant', 'responses', 'fields', 'requirements');
    }

    private function context(): array
    {
        $service = app(DynamicProcedureFormService::class);
        $type = $service->loadType($this->integer('procedure_type_id'));
        $variant = $service->variant($type, $this->filled('procedure_variant_id') ? $this->integer('procedure_variant_id') : null, (array) $this->input('eligibility', []));

        return [$service, $type, $variant];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['subject' => trim((string) $this->input('subject')), 'description' => trim((string) $this->input('description'))]);
    }

    public function messages(): array
    {
        return ['documents.max' => 'Puedes adjuntar como máximo cinco archivos.', 'documents.*.max' => 'Cada archivo debe pesar como máximo 5 MB.', 'confirmation.accepted' => 'Debes confirmar que revisaste la información antes de enviar.', 'responses.*.required' => 'Este campo configurado es obligatorio.'];
    }
}
