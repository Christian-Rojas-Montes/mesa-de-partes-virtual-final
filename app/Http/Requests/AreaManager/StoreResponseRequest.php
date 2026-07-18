<?php

namespace App\Http\Requests\AreaManager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreResponseRequest extends FormRequest
{
    /** @var list<string> */
    private const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /** @var list<string> */
    private const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function authorize(): bool
    {
        return $this->user()?->can('attendAssigned', $this->route('procedureRequest')) === true;
    }

    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:5000'],
            'document' => [
                'required',
                File::types(self::EXTENSIONS)->max('5mb'),
                'mimetypes:'.implode(',', self::MIME_TYPES),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('document');

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                return;
            }

            $extension = mb_strtolower($file->getClientOriginalExtension());
            $mimeType = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

            if (! in_array($extension, self::EXTENSIONS, true)) {
                $validator->errors()->add('document', 'La extensión del archivo no está permitida.');
            }

            if (! in_array($mimeType, self::MIME_TYPES, true)) {
                $validator->errors()->add('document', 'El contenido no corresponde a PDF, JPG o PNG.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['summary' => trim((string) $this->input('summary'))]);
    }

    public function messages(): array
    {
        return [
            'document.max' => 'El archivo debe pesar como máximo 5 MB.',
            'document.mimetypes' => 'Solo se permiten archivos PDF, JPG y PNG reconocidos.',
        ];
    }
}
