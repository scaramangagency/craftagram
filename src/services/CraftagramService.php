<?php
/**
 * craftagram plugin for Craft CMS 3.x
 *
 * Grab Instagram content through the Instagram Basic Display API
 *
 * @link      https://scaramanga.agency
 * @copyright Copyright (c) 2020 Scaramanga Agency
 */

namespace scaramangagency\craftagram\services;

use scaramangagency\craftagram\Craftagram;

use Craft;
use craft\base\Component;
use putyourlightson\logtofile\LogToFile;

/**
 * @author    Scaramanga Agency
 * @package   Craftagram
 * @since     1.0.0
 */
class CraftagramService extends Component
{
    
    public static function refreshToken() {
        $ch = curl_init();
            
        $params = [
            "access_token" => Craftagram::getInstance()->getSettings()->longAccessToken,
            "grant_type" => "ig_refresh_token"
        ];

        curl_setopt($ch, CURLOPT_URL,"https://graph.instagram.com/refresh_access_token?".http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);

        try {
            $expires = json_decode($res)->expires_in;
            LogToFile::info("Successfully refreshed authentication token. Expires in " . $expires, "craftagram");
        } catch (Exception $e) {
            LogToFile::error("Failed to refresh authentication token. Error: " . $res, "craftagram");
        }

        return true;
    }

    public static function getShortAccessToken($code) {
        $ch = curl_init();
            
        $params = [
            "client_id" => Craftagram::getInstance()->getSettings()->appId,
            "client_secret" => Craftagram::getInstance()->getSettings()->appSecret,
            "grant_type" => "authorization_code",
            "redirect_uri" => Craft::$app->sites->primarySite->baseUrl . "/actions/craftagram/default/auth",
            "code" => $code
        ];

        curl_setopt($ch, CURLOPT_URL,"https://api.instagram.com/oauth/access_token");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);
        $shortAccessToken = json_decode($res)->access_token;

        return CraftagramService::getLongAccessToken($shortAccessToken);
    }


    public static function getLongAccessToken($shortAccessToken) {
        $ch = curl_init();

        $params = [
            "client_secret" => Craftagram::getInstance()->getSettings()->appSecret,
            "grant_type" => "ig_exchange_token",
            "access_token" => $shortAccessToken
        ];

        curl_setopt($ch, CURLOPT_URL,"https://graph.instagram.com/access_token?".http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);
        $token = json_decode($res)->access_token;

        Craftagram::getInstance()->setSettings(["longAccessToken" => $token]);
        return $token;
    }


    public static function getInstagramFeed($limit, $after) {
        $ch = curl_init();

        if ($after != "") {
            $params = [
                "fields" => "caption,id,media_type,media_url,permalink,thumbnail_url,timestamp,username",
                "access_token" => Craftagram::getInstance()->getSettings()->longAccessToken,
                "limit" => $limit,
                "after" => $after
            ];
        } else {
            $params = [
                "fields" => "caption,id,media_type,media_url,permalink,thumbnail_url,timestamp,username",
                "access_token" => Craftagram::getInstance()->getSettings()->longAccessToken,
                "limit" => $limit
            ];
    
        }

        curl_setopt($ch, CURLOPT_URL,"https://graph.instagram.com/me/media?".http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res);
    }
}
