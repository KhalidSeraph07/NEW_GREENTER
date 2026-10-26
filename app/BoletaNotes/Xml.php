<?php

declare(strict_types=1);

namespace App\BoletaNotes;

use App\Invoices\InvoiceInput;
use DateTimeImmutable;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Xml\Builder\NoteBuilder;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Greenter\XMLSecLibs\XMLSecurityDSig;
use Greenter\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

final class Xml
{
    public function note(array $s): array
    {
        $source = $s['original']; $original = $source['snapshot'];
        if (config('h5b.mode') !== 'fictitious' || $s['mode'] !== 'fictitious' || $original['document_type'] !== '03'
            || $original['issuer_ruc'] !== '00000000000' || ($original['series'] ?? null) !== 'B000'
            || !in_array([$s['document_type'], $s['reason_code'], $s['series']], [['07','01','BC00'],['08','02','BD00']], true)) {
            throw new RuntimeException('Solo notas H5-B ficticias de boleta.');
        }
        $decimal = fn ($key) => (float) InvoiceInput::decimal($s[$key]);
        $item = (new SaleDetail())->setCodProducto('H4-TEST')->setUnidad('NIU')->setDescripcion($s['description'])
            ->setCantidad((float) InvoiceInput::decimal($s['quantity_milli'], 3))->setMtoValorUnitario($decimal('unit_price_cents'))
            ->setMtoValorVenta($decimal('subtotal_cents'))->setMtoBaseIgv($decimal('subtotal_cents'))
            ->setPorcentajeIgv(18)->setTipAfeIgv('10')->setIgv($decimal('tax_cents'))->setTotalImpuestos($decimal('tax_cents'))
            ->setMtoPrecioUnitario((float) InvoiceInput::decimal(intdiv($s['unit_price_cents'] * 118 + 50, 100)));
        $note = (new Note())->setUblVersion('2.1')->setTipoDoc($s['document_type'])->setSerie($s['series'])->setCorrelativo((string) $s['number'])
            ->setFechaEmision(new DateTimeImmutable($s['emission_date'].'T12:00:00-05:00'))->setTipoMoneda('PEN')
            ->setCodMotivo($s['reason_code'])->setDesMotivo($s['reason'])->setTipDocAfectado('03')
            ->setNumDocfectado('B000-'.$source['number'])
            ->setCompany((new Company())->setRuc('00000000000')->setRazonSocial('EMISOR FICTICIO H4')
                ->setAddress((new Address())->setUbigueo('000000')->setDepartamento('FICTICIO')->setProvincia('FICTICIO')
                    ->setDistrito('FICTICIO')->setDireccion('DIRECCION FICTICIA')->setCodigoPais('PE')))
            ->setClient((new Client())->setTipoDoc('1')->setNumDoc('00000000')->setRznSocial('CONSUMIDOR FICTICIO H4'))
            ->setMtoOperGravadas($decimal('subtotal_cents'))->setValorVenta($decimal('subtotal_cents'))
            ->setMtoIGV($decimal('tax_cents'))->setTotalImpuestos($decimal('tax_cents'))
            ->setSubTotal($decimal('total_cents'))->setMtoImpVenta($decimal('total_cents'))->setDetails([$item])
            ->setLegends([(new Legend())->setCode('1000')->setValue('H5-B FICTICIO SIN VALIDEZ FISCAL')]);
        $unsigned = (new NoteBuilder())->build($note);
        return $this->sign($unsigned);
    }

    public function summary(array $s): array
    {
        if (config('h5b.mode') !== 'fictitious') { throw new RuntimeException('Solo RC H5-B ficticio.'); }
        $details = [];
        foreach ($s['members'] as $m) {
            if (!in_array([$m['document_type'], $m['series']], [['07','BC00'],['08','BD00']], true)
                || $m['original_type'] !== '03' || !preg_match('/\AB000-[1-9][0-9]{0,7}\z/', $m['original_number'])) {
                throw new RuntimeException('Referencia RC de nota invalida.');
            }
            $details[] = (new \Greenter\Model\Summary\SummaryDetail())->setTipoDoc($m['document_type'])->setSerieNro($m['series'].'-'.$m['number'])
                ->setClienteTipo('1')->setClienteNro('00000000')->setEstado('1')
                ->setDocReferencia((new \Greenter\Model\Sale\Document())->setTipoDoc('03')->setNroDoc($m['original_number']))
                ->setTotal((float) InvoiceInput::decimal($m['total_cents']))
                ->setMtoOperGravadas((float) InvoiceInput::decimal($m['subtotal_cents']))
                ->setMtoIGV((float) InvoiceInput::decimal($m['tax_cents']))->setPorcentajeIgv(18);
        }
        $summary = (new \Greenter\Model\Summary\Summary())->setCompany((new Company())->setRuc('00000000000')->setRazonSocial('EMISOR FICTICIO H4'))
            ->setMoneda('PEN')->setCorrelativo((string) $s['number'])
            ->setFecGeneracion(new DateTimeImmutable($s['reference_date'].'T12:00:00-05:00'))
            ->setFecResumen(new DateTimeImmutable($s['issue_date'].'T12:00:00-05:00'))->setDetails($details);
        if ($summary->getName() !== $s['name']) { throw new RuntimeException('Identidad RC inconsistente.'); }
        return $this->sign((new \Greenter\Xml\Builder\SummaryBuilder())->build($summary));
    }

    private function sign(string $unsigned): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'H5-B TEST ONLY NO FISCAL VALIDITY'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        if (!$cert || !openssl_pkey_export($key, $private) || !openssl_x509_export($cert, $public)) {
            throw new RuntimeException('Certificado de prueba H5-B no generado.');
        }
        $signer = new SignedXml(); $signer->setCertificate($private.$public);
        $signer->setSignatureAlgorithm(XMLSecurityKey::RSA_SHA256); $signer->setDigestAlgorithm(XMLSecurityDSig::SHA256);
        $signed = $signer->signXml($unsigned); unset($private, $key, $signer);
        if (!(new SignedXml())->verifyXml($signed)) { throw new RuntimeException('Firma local H5-B invalida.'); }
        return ['unsigned' => $unsigned, 'xml' => $signed, 'certificate' => $public];
    }
}
