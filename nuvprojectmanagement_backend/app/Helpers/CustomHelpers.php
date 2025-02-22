<?php
namespace App\Helpers\CustomHelpers;

class WebEncryption
{
    public static function securePassword($password, $username)
    {
        $securePassword = crypt(md5($password), md5($username));
        $securePassword = base64_encode($securePassword);
        return $securePassword;
    }

    function encryptText($wordString)
    {
        $wordString = openssl_encrypt($wordString, 'aes-256-cbc', '7fodPfgRpk4APyGl');
        $wordString = base64_encode($wordString);
        return $wordString;
    }
    
    function decryptText($wordString)
    {
        $wordString = base64_decode($wordString);
        $wordString = openssl_decrypt($wordString, 'aes-256-cbc', '7fodPfgRpk4APyGl');
        return $wordString;
    }
}