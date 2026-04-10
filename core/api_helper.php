<?php

function checkDomainAvailability($sld, $tld) {
    if (empty($sld) || empty($tld)) {
        return ['status' => 'error', 'message' => 'Detail domain tidak valid.'];
    }

    $domain = $sld . '.' . $tld;

    $has_dns = @checkdnsrr($domain, 'ANY') || @checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'NS');

    if ($has_dns) {
        return [
            'status' => 'notavailable',
            'message' => 'Domain sudah terdaftar.'
        ];
    } else {
        return [
            'status' => 'available',
            'message' => 'Domain tersedia!'
        ];
    }
}
