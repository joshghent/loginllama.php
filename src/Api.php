<?php

class Api {
    private $headers;
    private $baseUrl;

    public function __construct($defaultHeaders, $url) {
        $this->headers = array_merge([
            "source" => "sdk",
            "X-LOGINLLAMA-SOURCE" => "php-sdk",
            "X-LOGINLLAMA-VERSION" => "1",
            "Content-Type" => "application/json"
        ], $defaultHeaders);
        $this->baseUrl = $url;
    }

    public function get($url) {
        $ch = curl_init($this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error: $httpCode");
        }
        curl_close($ch);
        return json_decode($response, true);
    }

    public function post($url, $params = []) {
        $ch = curl_init($this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error: $httpCode");
        }
        curl_close($ch);
        $decoded = json_decode($response, true);
        return $this->transformJsonApiResponse($decoded);
    }

    private function transformJsonApiResponse($decoded) {
        if (!is_array($decoded)) {
            return $decoded;
        }

        if (isset($decoded['errors']) && is_array($decoded['errors']) && count($decoded['errors']) > 0) {
            $firstError = $decoded['errors'][0];
            return [
                'status' => 'error',
                'message' => $firstError['detail'] ?? $firstError['title'] ?? 'Unknown error',
                'codes' => [],
                'risk_score' => 0,
                'environment' => $decoded['meta']['environment'] ?? 'unknown',
                'error' => $firstError['code'] ?? 'unknown_error',
                'meta' => $decoded['meta'] ?? null
            ];
        }

        if (isset($decoded['data']['attributes']) && is_array($decoded['data']['attributes'])) {
            $attrs = $decoded['data']['attributes'];
            $status = ($attrs['status'] ?? '') === 'pass' ? 'success' : 'error';

            return [
                'status' => $status,
                'message' => $attrs['message'] ?? '',
                'codes' => $attrs['risk_codes'] ?? [],
                'risk_score' => $attrs['risk_score'] ?? 0,
                'environment' => $decoded['meta']['environment'] ?? 'production',
                'unrecognized_device' => $attrs['unrecognized_device'] ?? null,
                'authentication_outcome' => $attrs['authentication_outcome'] ?? null,
                'email_sent' => $decoded['meta']['email_sent'] ?? null,
                'meta' => $decoded['meta'] ?? null
            ];
        }

        return $decoded;
    }
}
