<?php

declare(strict_types=1);

namespace App\Invoices;

use RuntimeException;
use Throwable;
use ZipArchive;

final class InvoiceEvidence
{
    private const FILES = ['unsigned' => 'unsigned.xml', 'xml' => 'signed.xml', 'certificate' => 'certificate-test.pem', 'zip' => 'invoice-test.zip'];

    public function save(string $id, array $contents): array
    {
        $directory = $this->directory($id);
        if (is_dir($directory)) {
            throw new RuntimeException('La evidencia existente no se sobrescribe.');
        }
        if (!mkdir($directory, 0700, true)) {
            throw new RuntimeException('No se pudo crear el almacenamiento privado.');
        }
        try {
            foreach (['unsigned', 'xml', 'certificate'] as $kind) {
                $path = $directory.'/'.self::FILES[$kind];
                if (file_put_contents($path, $contents[$kind], LOCK_EX) !== strlen($contents[$kind])) {
                    throw new RuntimeException('No se pudo conservar la evidencia completa.');
                }
                chmod($path, 0600);
            }
            $zip = new ZipArchive();
            if ($zip->open($directory.'/'.self::FILES['zip'], ZipArchive::CREATE | ZipArchive::EXCL) !== true
                || !$zip->addFromString('invoice.xml', $contents['xml']) || !$zip->close()) {
                throw new RuntimeException('No se pudo conservar el ZIP.');
            }
            chmod($directory.'/'.self::FILES['zip'], 0600);
            $manifest = [];
            foreach (self::FILES as $kind => $filename) {
                $manifest[$kind] = ['sha256' => hash_file('sha256', $directory.'/'.$filename), 'bytes' => filesize($directory.'/'.$filename)];
            }
            return $manifest;
        } catch (Throwable $exception) {
            $this->discardUncommitted($id);
            throw $exception;
        }
    }

    public function verifiedPath(string $id, string $kind, array $manifest): string
    {
        if (!isset(self::FILES[$kind], $manifest[$kind])) {
            throw new RuntimeException('Clase de evidencia no permitida.');
        }
        $path = $this->directory($id).'/'.self::FILES[$kind];
        if (!is_file($path) || is_link($path) || filesize($path) !== $manifest[$kind]['bytes']
            || !hash_equals($manifest[$kind]['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('Evidencia ausente o alterada; operación detenida.');
        }
        return $path;
    }

    public function discardUncommitted(string $id): void
    {
        $directory = $this->directory($id);
        foreach (self::FILES as $filename) {
            $path = $directory.'/'.$filename;
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('No se pudo retirar evidencia de la operación fallida.');
            }
        }
        if (is_dir($directory) && !rmdir($directory)) {
            throw new RuntimeException('Quedó una carpeta privada de operación fallida.');
        }
    }

    private function directory(string $id): string
    {
        if (!preg_match('/\A[0-9a-f-]{36}\z/', $id)) {
            throw new RuntimeException('Identificador de evidencia inválido.');
        }
        $root = rtrim(config('h2.evidence_root'), '/');
        if (is_link($root) || is_link($root.'/'.$id)) {
            throw new RuntimeException('No se admite evidencia mediante directorios enlazados.');
        }
        return $root.'/'.$id;
    }
}
