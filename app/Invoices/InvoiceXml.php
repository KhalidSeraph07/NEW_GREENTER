<?php

declare(strict_types=1);

namespace App\Invoices;

use DateTimeImmutable;
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
use RuntimeException;

final class InvoiceXml
{
    public function render(array $snapshot): array
    {
        if ($snapshot['issuer_ruc'] !== '00000000000' || $snapshot['receiver_ruc'] !== '00000000000'
            || $snapshot['mode'] !== 'fictitious' || $snapshot['tax_percent'] !== 18) {
            throw new RuntimeException('H2 solo admite la configuración fiscal ficticia.');
        }
        // Greenter tipa sus modelos como float: conversión solo para serializar importes ya calculados.
        $decimal = fn (string $key) => (float) InvoiceInput::decimal($snapshot[$key]);
        $detail = (new SaleDetail())
            ->setCodProducto('H2-TEST')->setUnidad('NIU')->setDescripcion($snapshot['description'])
            ->setCantidad((float) InvoiceInput::decimal($snapshot['quantity_milli'], 3))
            ->setMtoValorUnitario($decimal('unit_price_cents'))
            ->setMtoValorVenta($decimal('subtotal_cents'))->setMtoBaseIgv($decimal('subtotal_cents'))
            ->setPorcentajeIgv(18)->setTipAfeIgv('10')->setIgv($decimal('tax_cents'))
            ->setTotalImpuestos($decimal('tax_cents'));
        $unitGrossCents = intdiv($snapshot['unit_price_cents'] * 118 + 50, 100);
        $detail->setMtoPrecioUnitario((float) InvoiceInput::decimal($unitGrossCents));
        $invoice = (new Invoice())->setUblVersion('2.1')->setTipoOperacion('0101')->setTipoDoc('01')
            ->setSerie($snapshot['series'])->setCorrelativo((string) $snapshot['number'])
            ->setFechaEmision(new DateTimeImmutable($snapshot['emitted_at']))
            ->setTipoMoneda('PEN')->setFormaPago(new FormaPagoContado())
            ->setCompany((new Company())->setRuc('00000000000')->setRazonSocial($snapshot['issuer_name'])
                ->setAddress((new Address())->setUbigueo('000000')->setDepartamento('FICTICIO')
                    ->setProvincia('FICTICIO')->setDistrito('FICTICIO')->setDireccion('DIRECCION FICTICIA')->setCodigoPais('PE')))
            ->setClient((new Client())->setTipoDoc('6')->setNumDoc('00000000000')->setRznSocial($snapshot['receiver_name']))
            ->setMtoOperGravadas($decimal('subtotal_cents'))->setValorVenta($decimal('subtotal_cents'))
            ->setMtoIGV($decimal('tax_cents'))->setTotalImpuestos($decimal('tax_cents'))
            ->setSubTotal($decimal('total_cents'))->setMtoImpVenta($decimal('total_cents'))->setDetails([$detail])
            ->setLegends([(new Legend())->setCode('1000')->setValue('IMPORTE DE PRUEBA H2 - SIN VALIDEZ FISCAL')]);
        $unsigned = (new InvoiceBuilder())->build($invoice);
        // Clave efímera: no carga ficheros, PFX ni credenciales del contribuyente.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $request = openssl_csr_new(['commonName' => 'H2 TEST ONLY - NO FISCAL VALIDITY'], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($request, null, $key, 1, ['digest_alg' => 'sha256']);
        if (!$certificate || !openssl_pkey_export($key, $privatePem) || !openssl_x509_export($certificate, $certificatePem)) {
            throw new RuntimeException('No se pudo generar el certificado de prueba.');
        }
        $signer = new SignedXml();
        $signer->setCertificate($privatePem.$certificatePem);
        $signer->setSignatureAlgorithm(XMLSecurityKey::RSA_SHA256);
        $signer->setDigestAlgorithm(XMLSecurityDSig::SHA256);
        $signed = $signer->signXml($unsigned);
        if (!(new SignedXml())->verifyXml($signed)) {
            throw new RuntimeException('La firma local no se pudo verificar.');
        }
        return ['unsigned' => $unsigned, 'xml' => $signed, 'certificate' => $certificatePem];
    }
}
