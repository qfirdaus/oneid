<?php

declare(strict_types=1);

/** Return true when an IP address belongs to an exact address or CIDR range. */
function oneid_ip_matches_network(string $ipAddress, string $network): bool
{
    $network = trim($network);
    if ($network === '') {
        return false;
    }

    [$baseAddress, $prefixText] = array_pad(explode('/', $network, 2), 2, null);
    $ipBinary = @inet_pton($ipAddress);
    $baseBinary = @inet_pton(trim($baseAddress));
    if ($ipBinary === false || $baseBinary === false || strlen($ipBinary) !== strlen($baseBinary)) {
        return false;
    }

    $maximumBits = strlen($ipBinary) * 8;
    $prefix = $prefixText === null ? $maximumBits : filter_var(
        $prefixText,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => $maximumBits]]
    );
    if ($prefix === false) {
        return false;
    }

    $wholeBytes = intdiv((int) $prefix, 8);
    $remainingBits = (int) $prefix % 8;
    if ($wholeBytes > 0
        && substr($ipBinary, 0, $wholeBytes) !== substr($baseBinary, 0, $wholeBytes)
    ) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($baseBinary[$wholeBytes]) & $mask);
}

function oneid_is_trusted_proxy(string $ipAddress): bool
{
    $configured = explode(',', (string) oneid_config('ONEID_TRUSTED_PROXY_CIDRS', ''));
    foreach ($configured as $network) {
        if (oneid_ip_matches_network($ipAddress, $network)) {
            return true;
        }
    }
    return false;
}

/**
 * Resolve the client without trusting user-controlled forwarding headers.
 *
 * The forwarded chain is inspected from right to left. Trusted proxy hops are
 * discarded and the first untrusted, valid address is the originating client.
 */
function oneid_client_ip(array $server): string
{
    $peer = trim((string) ($server['REMOTE_ADDR'] ?? ''));
    if (filter_var($peer, FILTER_VALIDATE_IP) === false) {
        return '0.0.0.0';
    }
    if (!oneid_is_trusted_proxy($peer)) {
        return $peer;
    }

    $forwarded = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded === '') {
        $realIp = trim((string) ($server['HTTP_X_REAL_IP'] ?? ''));
        return filter_var($realIp, FILTER_VALIDATE_IP) !== false ? $realIp : $peer;
    }

    $chain = array_map('trim', explode(',', $forwarded));
    $chain[] = $peer;
    for ($index = count($chain) - 1; $index >= 0; $index--) {
        $candidate = $chain[$index];
        if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
            continue;
        }
        if (!oneid_is_trusted_proxy($candidate)) {
            return $candidate;
        }
    }
    return $peer;
}

function getUserIP(): string
{
    return oneid_client_ip($_SERVER);
}
