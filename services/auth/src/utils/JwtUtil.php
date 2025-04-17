<?php

require_once __DIR__ . '/../../config/jwt.php';

class JwtUtil {
    
    public static function generateToken($user) {
        $issuedAt = time();
        $expire = $issuedAt + JWT_EXPIRE;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role_name']
            ]
        ];
        
        return self::encode($payload);
    }
    
    public static function validateToken($token) {
        try {
            $decoded = self::decode($token);
            
            if ($decoded === false || !isset($decoded['exp']) || !isset($decoded['data'])) {
                return false;
            }
            
            // Check if token is expired
            if ($decoded['exp'] < time()) {
                return false;
            }
            
            return $decoded['data'];
        } catch (Exception $e) {
            return false;
        }
    }
    
    private static function encode($payload) {
        $header = [
            'alg' => JWT_ALGORITHM,
            'typ' => 'JWT'
        ];
        
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }
    
    private static function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) != 3) {
            return false;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        return $payload;
    }
    
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
} 