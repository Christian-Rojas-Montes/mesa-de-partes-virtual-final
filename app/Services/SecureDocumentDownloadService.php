<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureDocumentDownloadService
{
    private const MIME_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function download(RequestDocument $document, User $actor): StreamedResponse
    {
        abort_unless($document->disk === PrivateDocumentStorage::DISK, 404);
        abort_unless($this->validPath($document->path) && Storage::disk($document->disk)->exists($document->path), 404);
        $extension = self::MIME_EXTENSIONS[$document->mime_type] ?? null;
        abort_unless($extension !== null && hash_equals($extension, strtolower($document->extension)), 404);

        $request = $document->procedureRequest;
        AuditLog::create([
            'user_id' => $actor->id, 'action' => 'private_document_downloaded',
            'auditable_type' => $request->getMorphClass(), 'auditable_id' => $request->id,
            'details' => ['document_id' => $document->id, 'sensitive' => (bool) $document->requirement?->sensitive],
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);

        $filename = Str::slug($request->tracking_code).'-documento-'.$document->id.'.'.$extension;

        return Storage::disk($document->disk)->download($document->path, $filename, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function validPath(string $path): bool
    {
        return $path !== '' && ! str_contains($path, '..') && ! str_contains($path, '\\')
            && preg_match('#^[a-z0-9/_-]+\.(pdf|jpg|png)$#i', $path) === 1;
    }
}
