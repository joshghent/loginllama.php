<?php
define('API_ENDPOINT', 'https://loginllama.app/api/v1');

class LoginCheckStatus {
    const VALID = "login_valid";
    const IP_ADDRESS_SUSPICIOUS = "ip_address_suspicious";
    const DEVICE_FINGERPRINT_SUSPICIOUS = "device_fingerprint_suspicious";
    const LOCATION_FINGERPRINT_SUSPICIOUS = "location_fingerprint_suspicious";
    const BEHAVIORAL_FINGERPRINT_SUSPICIOUS = "behavioral_fingerprint_suspicious";
    const KNOWN_TOR_EXIT_NODE = "known_tor_exit_node";
    const KNOWN_PROXY = "known_proxy";
    const KNOWN_VPN = "known_vpn";
    const KNOWN_BOTNET = "known_botnet";
    const KNOWN_BOT = "known_bot";
    const IP_ADDRESS_NOT_USED_BEFORE = "ip_address_not_used_before";
    const DEVICE_FINGERPRINT_NOT_USED_BEFORE = "device_fingerprint_not_used_before";
    const AI_DETECTED_SUSPICIOUS = "ai_detected_suspicious";
}

class LoginLlama {
    private $api;
    private $token;

    public function __construct($apiToken = null, Api $api = null) {
        // allow passing in your own api instance for testing
        $this->api = $api ?: new Api(["X-API-KEY" => $apiToken], API_ENDPOINT);
        $this->token = $apiToken ?: getenv("LOGINLLAMA_API_KEY");
    }

    public function check_login($params) {
        if (isset($params['request'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        } else {
            $ip_address = $params['ip_address'];
            $user_agent = $params['user_agent'];
        }

        if (!$ip_address) {
            throw new Exception("ip_address is required");
        }
        if (!$user_agent) {
            throw new Exception("user_agent is required");
        }
        if (!$params['identity_key']) {
            throw new Exception("identity_key is required");
        }

        return $this->api->post("/login/check", [
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'identity_key' => $params['identity_key']
        ]);
    }
}
