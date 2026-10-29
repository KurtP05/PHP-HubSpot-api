<?php

const HUBSPOT_API = 'https://api.hubapi.com';
const CONTACT_PROPERTIES = ['firstname', 'lastname', 'email', 'phone'];

function hubspot_request(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init(HUBSPOT_API . $path);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . HUBSPOT_TOKEN,
            'Content-Type: application/json',
        ],
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        throw new RuntimeException("Couldn't reach HubSpot: " . curl_error($ch));
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = json_decode($response, true) ?? [];

    if ($status >= 400) {
        $message = $data['message'] ?? "HubSpot returned HTTP $status";
        throw new RuntimeException($message, $status);
    }

    return $data;
}

function get_contacts(int $limit = 50): array
{
    $query = http_build_query([
        'limit'      => $limit,
        'properties' => implode(',', CONTACT_PROPERTIES),
    ]);

    $data = hubspot_request('GET', "/crm/v3/objects/contacts?$query");
    return $data['results'] ?? [];
}

function search_contacts(string $term, int $limit = 50): array
{
    $data = hubspot_request('POST', '/crm/v3/objects/contacts/search', [
        'query'      => $term,
        'limit'      => $limit,
        'properties' => CONTACT_PROPERTIES,
    ]);
    return $data['results'] ?? [];
}

function create_contact(array $properties): array
{
    return hubspot_request('POST', '/crm/v3/objects/contacts', [
        'properties' => $properties,
    ]);
}
