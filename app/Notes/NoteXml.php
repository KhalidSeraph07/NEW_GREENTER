<?php

declare(strict_types=1);

namespace App\Notes;

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

final class NoteXml
{
    public function render(array $s): array
    {
        $source = $s['original']; $original = $source['snapshot'];
        if (config('h5.mode') !== 'fictitious' || $s['mode'] !== 'fictitious' || $source['document_type'] !== '01'
            || $source['issuer_ruc'] !== '00000000000' || $original['receiver_ruc'] !== '00000000000'
            || !in_array([$s['document_type'], $s['reason_code'], $s['series']], [['07','01','FC00'],['08','02','FD00']], true)) {
            throw new RuntimeException('Solo notas H5-A ficticias de factura.');
        }
        $decimal = fn ($key) => (float) InvoiceInput::decimal($s[$key]);
        $item = (new SaleDetail())->setCodProducto('H2-TEST')->setUnidad('NIU')->setDescripcion($s['description'])
            ->setCantidad((float) InvoiceInput::decimal($s['quantity_milli'], 3))->setMtoValorUnitario($decimal('unit_price_cents'))
            ->setMtoValorVenta($decimal('subtotal_cents'))->setMtoBaseIgv($decimal('subtotal_cents'))
            ->setPorcentajeIgv(18)->setTipAfeIgv('10')->setIgv($decimal('tax_cents'))->setTotalImpuestos($decimal('tax_cents'))
            ->setMtoPrecioUnitario((float) InvoiceInput::decimal(intdiv($s['unit_price_cents'] * 118 + 50, 100)));
        $note = (new Note())->setUblVersion('2.1')->setTipoDoc($s['document_type'])->setSerie($s['series'])->setCorrelativo((string) $s['number'])
            ->setFechaEmision(new DateTimeImmutable($s['emission_date'].'T12:00:00-05:00'))->setTipoMoneda('PEN')
            ->setCodMotivo($s['reason_code'])->setDesMotivo($s['reason'])->setTipDocAfectado('01')
            ->setNumDocfectado($source['series'].'-'.$source['number'])
            ->setCompany((new Company())->setRuc('00000000000')->setRazonSocial($original['issuer_name'])
                ->setAddress((new Address())->setUbigueo('000000')->setDepartamento('FICTICIO')->setProvincia('FICTICIO')
                    ->setDistrito('FICTICIO')->setDireccion('DIRECCION FICTICIA')->setCodigoPais('PE')))
            ->setClient((new Client())->setTipoDoc('6')->setNumDoc('00000000000')->setRznSocial($original['receiver_name']))
            ->setMtoOperGravadas($decimal('subtotal_cents'))->setValorVenta($decimal('subtotal_cents'))
            ->setMtoIGV($decimal('tax_cents'))->setTotalImpuestos($decimal('tax_cents'))
            ->setSubTotal($decimal('total_cents'))->setMtoImpVenta($decimal('total_cents'))->setDetails([$item])
            ->setLegends([(new Legend())->setCode('1000')->setValue('H5-A FICTICIO SIN VALIDEZ FISCAL')]);
        $unsigned = (new NoteBuilder())->build($note);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'H5-A TEST ONLY NO FISCAL VALIDITY'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        if (!$cert || !openssl_pkey_export($key, $private) || !openssl_x509_export($cert, $public)) {
            throw new RuntimeException('Certificado de prueba H5-A no generado.');
        }
        $signer = new SignedXml(); $signer->setCertificate($private.$public);
        $signer->setSignatureAlgorithm(XMLSecurityKey::RSA_SHA256); $signer->setDigestAlgorithm(XMLSecurityDSig::SHA256);
        $signed = $signer->signXml($unsigned); unset($private, $key, $signer);
        if (!(new SignedXml())->verifyXml($signed)) { throw new RuntimeException('Firma local H5-A invalida.'); }
        return ['unsigned' => $unsigned, 'xml' => $signed, 'certificate' => $public];
    }
}
