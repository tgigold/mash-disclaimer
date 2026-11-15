<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

/**
 * One uploaded file. Name and type as sent by the browser are kept only for
 * display – the server checks size and content itself (S-42).
 */
final readonly class UploadedFile
{
    public function __construct(
        public string $clientName,
        public string $tmpPath,
        public int $size,
        public int $error,
    ) {
    }

    public function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->tmpPath !== '' && is_uploaded_file($this->tmpPath);
    }

    /**
     * Only single file fields; arrays of files (name="f[]") are ignored (E-2).
     *
     * @param array<mixed> $files $_FILES
     * @return array<string, self>
     */
    public static function fromGlobals(array $files): array
    {
        $result = [];
        foreach ($files as $field => $file) {
            if (!is_array($file) || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)
                || !is_int($file['size'] ?? null) || !is_int($file['error'] ?? null)) {
                continue;
            }
            $result[(string) $field] = new self($file['name'], $file['tmp_name'], $file['size'], $file['error']);
        }

        return $result;
    }
}
