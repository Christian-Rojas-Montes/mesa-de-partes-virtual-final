<?php

namespace App\Services;

use App\Models\ProcedureRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PrivateDocumentStorage
{
    public const DISK = 'private';

    /** @return array{disk: string, path: string, stored_name: string, extension: string, mime_type: string, size_bytes: int, checksum_sha256: string} */
    public function store(UploadedFile $file, ProcedureRequest $procedureRequest): array
    {
        return $this->storeInDirectory($file, 'requests/'.$procedureRequest->id);
    }

    /** @return array{disk: string, path: string, stored_name: string, extension: string, mime_type: string, size_bytes: int, checksum_sha256: string} */
    public function storeResponse(UploadedFile $file, ProcedureRequest $procedureRequest): array
    {
        return $this->storeInDirectory($file, 'responses/'.$procedureRequest->id);
    }

    /** @return array{disk: string, path: string, stored_name: string, extension: string, mime_type: string, size_bytes: int, checksum_sha256: string} */
    private function storeInDirectory(UploadedFile $file, string $directory): array
    {
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $fileInfo->file($file->getRealPath());
        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new RuntimeException('El contenido del archivo no corresponde a un formato permitido.'),
        };
        $checksum = hash_file('sha256', $file->getRealPath());

        if ($checksum === false) {
            throw new RuntimeException('No fue posible calcular la integridad del archivo.');
        }

        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = Storage::disk(self::DISK)->putFileAs($directory, $file, $storedName);

        if ($path === false) {
            throw new RuntimeException('No fue posible almacenar el documento.');
        }

        return [
            'disk' => self::DISK,
            'path' => $path,
            'stored_name' => $storedName,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $file->getSize(),
            'checksum_sha256' => $checksum,
        ];
    }

    /** @param list<string> $paths */
    public function delete(array $paths): void
    {
        try {
            Storage::disk(self::DISK)->delete($paths);
        } catch (Throwable) {
            report(new RuntimeException('No fue posible limpiar uno o más documentos temporales.'));
        }
    }
}
