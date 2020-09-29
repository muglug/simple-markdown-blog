<?php

namespace Muglug\Blog;

class GithubAuth
{
    public static function getToken(
        string $state,
        string $code,
        string $client_id,
        string $client_secret
    ) : string {
        $expected_state = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'], $client_secret);

        if ($state !== $expected_state) {
            throw new \UnexpectedValueException('States should match');
        }

        if (!$code) {
            throw new \UnexpectedValueException('No code sent');
        }

        $params = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code,
            'state' => $state,
        ];

        $payload = http_build_query($params);

        $github_url = 'https://github.com';

        // Prepare new cURL resource
        $ch = curl_init($github_url . '/login/oauth/access_token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // Set HTTP Header for POST request
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($payload)
            ]
        );

        // Submit the POST request
        $response = (string) curl_exec($ch);

        // Close cURL session handle
        curl_close($ch);

        if (!$response) {
            throw new \UnexpectedValueException('Response should exist');
        }

        $response_data = json_decode($response, true);

        return $response_data['access_token'];
    }
}

