<?php

namespace App\Services;

class NotificationPrivacySanitizer
{
    public static function sanitize(string $message): string
    {
        return trim((string) preg_replace('/\b(documento\s+m[eé]dico|historia\s+cl[ií]nica|sentencia(?:\s+judicial)?|diagn[oó]stico|archivo\s+adjunto|partida\s+de\s+nacimiento|acta(?:s)?\s+de\s+titulaci[oó]n|certificado(?:s)?\s+acad[eé]mico(?:s)?|fotograf[ií]a(?:s)?|copia(?:s)?\s+del\s+dni)\b/iu', 'información reservada', strip_tags($message)));
    }
}
