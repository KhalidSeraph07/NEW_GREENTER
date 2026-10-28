<?php
declare(strict_types=1);
namespace App\Corrections;

use App\Notes\Original;
use LogicException;
use ZipArchive;

final class PackingDefect
{
    public const BAD_ENTRY='H5C2-INVALID-ENTRY.xml';
    private static bool $armed=false;
    private static bool $used=false;

    public static function fixture(callable $prepare): mixed
    {
        Family::guard();
        if(self::$armed){throw new LogicException('Fixture anidado no permitido.');}
        self::$armed=true;self::$used=false;
        try{$r=$prepare();if(!self::$used){throw new LogicException('Fixture debe crear artefactos nuevos, no adoptar un replay.');}return $r;}
        finally{self::$armed=false;self::$used=false;}
    }
    public static function entry(string $normal): string
    {
        if(!self::$armed){return $normal;}Family::guard();
        if(self::$used){throw new LogicException('Un solo ZIP nuevo por fixture explicito.');}
        self::$used=true;return self::BAD_ENTRY;
    }
    public static function cause(string $family,mixed $record): array
    {
        self::checkZip($family,$record,self::BAD_ENTRY);
        return ['simulated'=>true,'code'=>'SIM-C2-ZIP-ENTRY','scope'=>'technical_packaging','family'=>$family,
            'document_id'=>$record->id,'request_sha256'=>$record->artifacts['zip']['sha256'],
            'xml_sha256'=>$record->artifacts['xml']['sha256'],'actual_entry'=>self::BAD_ENTRY,
            'expected_entry'=>Family::expectedEntry($family,$record),'snapshot_hash'=>Original::hash($record->snapshot)];
    }
    public static function assertRepaired(string $family,mixed $record): void {self::checkZip($family,$record,Family::expectedEntry($family,$record));}
    private static function checkZip(string $family,mixed $r,string $entry): void
    {
        Family::verifyFiles($family,$r);$zip=new ZipArchive();
        if($zip->open(Family::path($family,$r,'zip'))!==true){throw new LogicException('ZIP de ensayo ilegible.');}
        try{if($zip->numFiles!==1 || $zip->getNameIndex(0)!==$entry
            || !hash_equals($r->artifacts['xml']['sha256'],hash('sha256',$zip->getFromIndex(0)))){
                throw new LogicException('Defecto o subsanacion ZIP no acreditados.');}}
        finally{$zip->close();}
    }
}
