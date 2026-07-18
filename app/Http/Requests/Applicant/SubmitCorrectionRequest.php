<?php

namespace App\Http\Requests\Applicant;

use App\Models\RequestObservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SubmitCorrectionRequest extends FormRequest
{
    private const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function authorize(): bool
    {
        return $this->user()?->can('correct', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return [
            'observation_id' => ['required', 'integer', 'exists:request_observations,id'],
            'message' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => [
                'required',
                File::types(self::EXTENSIONS)->max('5mb'),
                'mimetypes:'.implode(',', self::MIME_TYPES),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $observation = RequestObservation::find($this->integer('observation_id'));

            if ($observation !== null && (
                $observation->procedure_request_id !== $this->route('procedureRequest')->id
                || $observation->resolved_at !== null
            )) {
                $validator->errors()->add('observation_id', 'La observación seleccionada no admite subsanación.');
            }

            foreach ($this->file('documents', []) as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $extension = mb_strtolower($file->getClientOriginalExtension());
                $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = (string) $fileInfo->file($file->getRealPath());

                if (! in_array($extension, self::EXTENSIONS, true) || ! in_array($mimeType, self::MIME_TYPES, true)) {
                    $validator->errors()->add('documents', 'Solo se admiten archivos PDF, JPG y PNG reconocidos.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['message' => trim((string) $this->input('message')) ?: null]);
    }
}
