<?php

declare(strict_types=1);

namespace App\Boletas;

use RuntimeException;
use Throwable;
use ZipArchive;

final class Artifacts
{
    private const FILES = ['unsigned' => 'unsigned.xml', 'xml' => 'signed.xml', 'certificate' => 'certificate-test.pem', 'zip' => 'document.zip'];

    public function save(string $id, string $name, array $contents): array
    {
        $dir = $this->directory($id);
        if (file_exists($dir) || !preg_match('/\A00000000000-(?:03-B000-\d+|RC-\d{8}-\d+)\z/', $name)) {
            throw new RuntimeException('Evidencia existente o nombre ficticio invalido.');
        }
        if (!mkdir($dir, 0700, true)) { throw new RuntimeException('No se pudo crear evidencia H4.'); }
        try {
            foreach (['unsigned', 'xml', 'certificate'] as $kind) {
                if (file_put_contents($dir.'/'.self::FILES[$kind], $contents[$kind], LOCK_EX) !== strlen($contents[$kind])) {
                    throw new RuntimeException('Escritura incompleta H4.');
                }
                chmod($dir.'/'.self::FILES[$kind], 0600);
            }
            $zip = new ZipArchive();
            if ($zip->open($dir.'/document.zip', ZipArchive::CREATE | ZipArchive::EXCL) !== true
                || !$zip->addFromString(\App\Corrections\PackingDefect::entry($name.'.xml'), $contents['xml']) || !$zip->close()) {
                throw new RuntimeException('ZIP H4 incompleto.');
            }
            chmod($dir.'/document.zip', 0600);
            $manifest = [];
            foreach (self::FILES as $kind => $file) {
                $manifest[$kind] = ['sha256' => hash_file('sha256', $dir.'/'.$file), 'bytes' => filesize($dir.'/'.$file)];
            }
            return $manifest;
        } catch (Throwable $error) { $this->discardOwn($id); throw $error; }
    }

    public function path(string $id, string $kind, array $manifest): string
    {
        if (!isset(self::FILES[$kind], $manifest[$kind])) { throw new RuntimeException('Evidencia H4 no permitida.'); }
        $path = $this->directory($id).'/'.self::FILES[$kind];
        clearstatcache(true, $path);
        if (!is_file($path) || is_link($path) || filesize($path) !== $manifest[$kind]['bytes']
            || !hash_equals($manifest[$kind]['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('Evidencia H4 ausente/alterada.');
        }
        return $path;
    }

    public function verify(string $id, array $manifest): void
    {
        foreach (array_keys(self::FILES) as $kind) { $this->path($id, $kind, $manifest); }
    }

    public function discardOwn(string $id): void
    {
        $dir = $this->directory($id);
        foreach (self::FILES as $file) {
            if (is_file($dir.'/'.$file) && !unlink($dir.'/'.$file)) { throw new RuntimeException('Limpieza de fixture propia fallida.'); }
        }
        if (is_dir($dir) && !rmdir($dir)) { throw new RuntimeException('Carpeta propia conservada para revision.'); }
    }

    public function directory(string $id): string
    {
        $root = rtrim(config('h4.evidence_root'), '/');
        if (!preg_match('/\A[0-9a-f-]{36}\z/', $id) || is_link($root) || is_link($root.'/'.$id)) {
            throw new RuntimeException('Ruta H4 invalida/enlazada.');
        }
        return $root.'/'.$id;
    }
}
