<?php

class Api {
    private $headers;
    private $baseUrl;

    public function __construct($defaultHeaders, $url) {
        $this->headers = array_merge([
            "X-LOGINLLAMA-SOURCE" => "node-sdk",
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
        return json_decode($response, true);
    }
}
