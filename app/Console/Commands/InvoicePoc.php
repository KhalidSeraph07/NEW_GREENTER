<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Composer\InstalledVersions;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Xml\Builder\InvoiceBuilder;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Greenter\XMLSecLibs\XMLSecurityDSig;
use Greenter\XMLSecLibs\XMLSecurityKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class InvoicePoc extends Command
{
    protected $signature = 'poc:invoice {--database : Probar PostgreSQL sin crear tablas}';

    protected $description = 'POC H1 local: datos/certificado ficticios, sin transporte SUNAT';

    private array $checks = [];

    public function handle(): int
    {
        $directory = storage_path('app/h1');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de evidencia sintética.');
        }
        // Una ejecución fallida no debe dejar como vigente el resultado verde anterior.
        $this->write($directory.'/result.json', json_encode(['status' => 'running', 'started_at' => date(DATE_ATOM)], JSON_THROW_ON_ERROR));
        $this->check(version_compare(PHP_VERSION, '8.3.36', '>='), 'PHP parcheado');
        $this->check(ini_get('session.upload_progress.enabled') === '0', 'Mitigación de sesiones cargada');
        $this->check(posix_geteuid() !== 0, 'Ejecución sin root');
        $this->check($this->hasNoDefaultRoute(), 'Sin ruta externa IPv4/IPv6');
        $this->check(!class_exists(\Greenter\See::class), 'Sin componente de transporte Greenter');

        // No hay parámetros ni lectura de credenciales/certificados: solo esta fixture sintética.
        $unsigned = (new InvoiceBuilder())->build($this->fixture());
        $document = $this->parse($unsigned);
        $this->check($document->documentElement->localName === 'Invoice', 'XML Invoice generado por Greenter');
        $this->check(str_contains($unsigned, '00000000000'), 'Identificador deliberadamente inválido y ficticio');

        [$privateKey, $certificate] = $this->testCertificate();
        $signer = new SignedXml();
        $signer->setCertificate($privateKey.$certificate);
        $signer->setSignatureAlgorithm(XMLSecurityKey::RSA_SHA256);
        $signer->setDigestAlgorithm(XMLSecurityDSig::SHA256);
        $signed = $signer->signXml($unsigned);
        unset($privateKey, $signer);

        $signedDocument = $this->parse($signed);
        $xpath = new DOMXPath($signedDocument);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $this->check($xpath->evaluate('string(//ds:SignatureMethod/@Algorithm)') === XMLSecurityKey::RSA_SHA256, 'RSA-SHA256 explícito');
        $this->check($xpath->evaluate('string(//ds:DigestMethod/@Algorithm)') === XMLSecurityDSig::SHA256, 'Digest SHA256 explícito');
        $this->check((new SignedXml())->verifyXml($signed), 'Firma y digest verificados por Greenter');

        $this->write($directory.'/invoice-unsigned.xml', $unsigned);
        $this->write($directory.'/invoice-signed.xml', $signed);
        $this->write($directory.'/certificate-test.pem', $certificate);
        $certificateInfo = openssl_x509_parse($certificate);
        $this->check($certificateInfo['subject']['CN'] === 'H1 TEST ONLY - NO FISCAL VALIDITY', 'Certificado autofirmado exclusivamente de prueba');
        $this->check(openssl_x509_verify($certificate, openssl_pkey_get_public($certificate)) === 1, 'Autofirma del certificado verificada');

        // xmlsec1 usa la clave pública esperada, no confía en la identidad que declare el XML.
        $this->check($this->verifyWithXmlsec($directory.'/invoice-signed.xml', $directory.'/certificate-test.pem'), 'Firma independiente xmlsec1 con certificado fijado');

        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $payable = $xpath->query('//cac:LegalMonetaryTotal/cbc:PayableAmount')->item(0);
        $this->check($payable !== null, 'Importe de fixture localizado');
        $payable->nodeValue = '119.00';
        $tampered = $signedDocument->saveXML();
        $this->write($directory.'/invoice-tampered.xml', $tampered);
        $this->check(!(new SignedXml())->verifyXml($tampered), 'Greenter rechaza importe alterado');
        $this->check(!$this->verifyWithXmlsec($directory.'/invoice-tampered.xml', $directory.'/certificate-test.pem'), 'xmlsec1 rechaza importe alterado');

        $badSignature = $this->parse($signed);
        $value = $badSignature->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'SignatureValue')->item(0);
        $originalValue = trim($value->textContent);
        $value->nodeValue = ($originalValue[0] === 'A' ? 'B' : 'A').substr($originalValue, 1);
        $this->write($directory.'/invoice-bad-signature.xml', $badSignature->saveXML());
        $this->check(!(new SignedXml())->verifyXml($badSignature->saveXML()), 'Greenter rechaza SignatureValue alterado');
        $this->check(!$this->verifyWithXmlsec($directory.'/invoice-bad-signature.xml', $directory.'/certificate-test.pem'), 'xmlsec1 rechaza SignatureValue alterado');

        [$wrongPrivateKey, $wrongCertificate] = $this->testCertificate();
        unset($wrongPrivateKey);
        $this->write($directory.'/certificate-wrong-test.pem', $wrongCertificate);
        $this->check(!$this->verifyWithXmlsec($directory.'/invoice-signed.xml', $directory.'/certificate-wrong-test.pem'), 'xmlsec1 rechaza clave pública distinta');

        $roundTrip = simplexml_load_string($signed, options: LIBXML_NONET);
        $this->check($roundTrip !== false && (new SignedXml())->verifyXml($roundTrip->asXML()), 'SimpleXML parseo/serialización conserva firma');
        for ($i = 0; $i < 100; $i++) {
            $xml = new \SimpleXMLElement('<a><b/></a>');
            get_object_vars($xml);
            $holder = new \stdClass();
            $holder->xml = $xml;
            $holder->self = $holder;
            unset($xml, $holder);
            gc_collect_cycles();
        }
        $this->check(true, 'Reproducción focalizada GC/SimpleXML: 100 ciclos sin caída');

        $zip = new ZipArchive();
        $this->check($zip->open($directory.'/invoice-test.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'ZIP creado');
        $zip->addFromString('invoice-signed.xml', $signed);
        $zip->close();
        $this->check($zip->open($directory.'/invoice-test.zip') === true, 'ZIP reabierto');
        $this->check($zip->getFromName('invoice-signed.xml') === $signed, 'ZIP conserva bytes de XML firmado');
        $zip->close();

        $database = $this->option('database') ? $this->checkDatabase() : 'No solicitado';
        $result = [
            'status' => 'passed',
            'scope' => 'H1 FICTICIO LOCAL - SIN VALIDEZ FISCAL - SIN SERVICIOS SUNAT',
            'generated_at' => date(DATE_ATOM),
            'versions' => [
                'php' => PHP_VERSION,
                'laravel/framework' => InstalledVersions::getPrettyVersion('laravel/framework'),
                'greenter/xml' => InstalledVersions::getPrettyVersion('greenter/xml'),
                'greenter/core' => InstalledVersions::getPrettyVersion('greenter/core'),
                'greenter/xmldsig' => InstalledVersions::getPrettyVersion('greenter/xmldsig'),
                'openssl' => OPENSSL_VERSION_TEXT,
                'libxml' => LIBXML_DOTTED_VERSION,
            ],
            'checks' => $this->checks,
            'database' => $database,
            'signed_xml_sha256' => hash('sha256', $signed),
            'certificate_sha256' => openssl_x509_fingerprint($certificate, 'sha256'),
            'limitations' => ['Sin validación fiscal/XSD/CDR/SUNAT', 'Sin cadena de confianza real', 'Certificado/clave nuevos por ejecución; XML no idéntico entre ejecuciones'],
        ];
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        $this->write($directory.'/result.json', $json);
        $this->line($json);

        return self::SUCCESS;
    }

    private function fixture(): Invoice
    {
        $company = (new Company())
            ->setRuc('00000000000')
            ->setRazonSocial('EMISOR FICTICIO H1 - SIN VALIDEZ FISCAL')
            ->setAddress((new Address())->setUbigueo('000000')->setDepartamento('FICTICIO')->setProvincia('FICTICIO')->setDistrito('FICTICIO')->setDireccion('DIRECCION DE PRUEBA')->setCodigoPais('PE'));
        $client = (new Client())->setTipoDoc('6')->setNumDoc('00000000000')->setRznSocial('RECEPTOR FICTICIO H1');
        $detail = (new SaleDetail())
            ->setCodProducto('H1-TEST')->setUnidad('NIU')->setDescripcion('PRODUCTO FICTICIO H1')->setCantidad(1)
            ->setMtoValorUnitario(100)->setMtoValorVenta(100)->setMtoBaseIgv(100)
            ->setPorcentajeIgv(18)->setIgv(18)->setTipAfeIgv('10')->setTotalImpuestos(18)->setMtoPrecioUnitario(118);

        // Valores fijos para probar generación/firma, sin cálculo ni decisión tributaria del producto.
        return (new Invoice())
            ->setUblVersion('2.1')->setTipoOperacion('0101')->setTipoDoc('01')->setSerie('F000')->setCorrelativo('1')
            ->setFechaEmision(new DateTimeImmutable('2026-10-25T12:00:00-05:00'))->setTipoMoneda('PEN')
            ->setCompany($company)->setClient($client)->setFormaPago(new FormaPagoContado())
            ->setMtoOperGravadas(100)->setMtoIGV(18)->setTotalImpuestos(18)
            ->setValorVenta(100)->setSubTotal(118)->setMtoImpVenta(118)
            ->setDetails([$detail])->setLegends([(new Legend())->setCode('1000')->setValue('CIENTO DIECIOCHO SOLES - PRUEBA H1')]);
    }

    private function testCertificate(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            throw new RuntimeException('No se pudo generar la clave exclusivamente de prueba.');
        }
        $request = openssl_csr_new(['commonName' => 'H1 TEST ONLY - NO FISCAL VALIDITY', 'organizationName' => 'FICTICIO H1'], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($request, null, $key, 1, ['digest_alg' => 'sha256']);
        if ($certificate === false || !openssl_pkey_export($key, $privatePem) || !openssl_x509_export($certificate, $certificatePem)) {
            throw new RuntimeException('No se pudo generar el certificado exclusivamente de prueba.');
        }

        return [$privatePem, $certificatePem];
    }

    private function verifyWithXmlsec(string $xmlPath, string $certificatePath): bool
    {
        // En xmlsec1 1.3 selecciona la única clave proporcionada; X509 del XML sigue inhabilitado.
        $process = new Process(['xmlsec1', '--verify', '--lax-key-search', '--enabled-key-data', 'rsa', '--enabled-reference-uris', 'empty', '--pubkey-cert-pem', $certificatePath, $xmlPath]);
        $process->setTimeout(15);
        $process->run();
        if ($process->getExitCode() === 127 || $process->getExitCode() === null) {
            throw new RuntimeException('Verificador independiente xmlsec1 no disponible.');
        }

        return $process->isSuccessful();
    }

    private function checkDatabase(): array
    {
        $info = DB::selectOne('SELECT version() AS version, current_database() AS database, current_user AS username');
        $this->check($info->database === config('database.connections.pgsql.database'), 'Laravel conecta a PostgreSQL con autenticación TCP');
        $settings = config('database.connections.pgsql');
        try {
            new PDO('pgsql:host='.$settings['host'].';port='.$settings['port'].';dbname='.$settings['database'], $settings['username'], $settings['password'].'-incorrect-H1');
        } catch (PDOException $exception) {
            $this->check(str_contains($exception->getMessage(), 'password authentication failed'), 'PostgreSQL rechaza contraseña errónea');

            return (array) $info;
        }

        throw new RuntimeException('La conexión con contraseña errónea fue aceptada.');
    }

    private function hasNoDefaultRoute(): bool
    {
        foreach (file('/proc/net/route') as $line) {
            $columns = preg_split('/\s+/', trim($line));
            if (($columns[1] ?? '') === '00000000' && ($columns[7] ?? '') === '00000000' && $columns[0] !== 'lo') {
                return false;
            }
        }
        foreach (file('/proc/net/ipv6_route') as $line) {
            $columns = preg_split('/\s+/', trim($line));
            if ($columns[0] === str_repeat('0', 32) && $columns[1] === '00' && end($columns) !== 'lo') {
                return false;
            }
        }

        return true;
    }

    private function parse(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        if (!$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('XML sintético no parseable.');
        }

        return $document;
    }

    private function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('No se pudo guardar evidencia sintética.');
        }
    }

    private function check(bool $condition, string $name): void
    {
        if (!$condition) {
            throw new RuntimeException('POC fallida: '.$name);
        }
        $this->checks[$name] = true;
    }
}
