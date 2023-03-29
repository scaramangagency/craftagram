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
use scaramangagency\craftagram\records\SettingsRecord;

use Craft;
use craft\base\Component;
use putyourlightson\logtofile\LogToFile;
use craft\helpers\Db;
use craft\helpers\App;

class CraftagramService extends Component {

    /**
     * Grab the long access token based on supplied site
     *
     * @return string
     */
    public function getLongAccessTokenSetting($siteId) {

        $params = [
            'craftagramSiteId' => $siteId
        ];

        $longAccessTokenRecord = SettingsRecord::findOne($params);

        if (!$longAccessTokenRecord) {
            LogToFile::info('An access token has not been obtained from Instagram', 'craftagram');
            return false;
        }

        return $longAccessTokenRecord->getAttribute('longAccessToken');
    }

    /**
     * Check if the API endpoint is secured for this site
     *
     * @return string
     */
    public function checkIfSecured($siteId) {

        if ($siteId == 0) {
            $siteId = Craft::$app->sites->primarySite->id;
        }

        $params = [
            'craftagramSiteId' => $siteId
        ];

        $isSecured = SettingsRecord::findOne($params);

        if (!$isSecured) {
            LogToFile::info('This site does not have a linked instagram account', 'craftagram');
            return false;
        }

        return $isSecured->getAttribute('secureApiEndpoint');
    }

    /**
     * Refresh token for a single site
     *
     * @return bool
     */
    public function refreshToken(int $siteId) {
        $longAccessTokenRecord = Craftagram::$plugin->craftagramService->getLongAccessTokenSetting($siteId);

        if (!$longAccessTokenRecord) {
            return false;
        }

        $ch = curl_init();

        $params = [
            'access_token' => $longAccessTokenRecord,
            'grant_type' => 'ig_refresh_token'
        ];

        curl_setopt($ch, CURLOPT_URL,'https://graph.instagram.com/refresh_access_token?'.http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);

        try {
            $expires = json_decode($res)->expires_in;
            LogToFile::info('Successfully refreshed authentication token. Expires in ' . $expires, 'craftagram');
        } catch (Exception $e) {
            LogToFile::error('Failed to refresh authentication token. Error: ' . $res, 'craftagram');

            return false;
        }

        return true;
    }

    /**
     * Get short access token from Instagram
     */
    public function getShortAccessToken($code, $siteId) {
        $ch = curl_init();

        $getSettings = [
            'craftagramSiteId' => $siteId
        ];

        $longAccessTokenRecord = SettingsRecord::findOne($getSettings);

        $params = [
            'client_id' => App::parseEnv($longAccessTokenRecord->appId),
            'client_secret' => App::parseEnv($longAccessTokenRecord->appSecret),
            'grant_type' => 'authorization_code',
            'redirect_uri' => rtrim(App::parseEnv(Craft::$app->sites->primarySite->baseUrl), '/') . '/actions/craftagram/default/auth',
            'code' => $code
        ];

        curl_setopt($ch, CURLOPT_URL,'https://api.instagram.com/oauth/access_token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);

        $shortAccessToken = json_decode($res)->access_token;

        return Craftagram::$plugin->craftagramService->getLongAccessToken($shortAccessToken, $siteId, $longAccessTokenRecord->appSecret);
    }

    /**
     * Get long access token from instagram and save it
     *
     * @return string
     */
    public function getLongAccessToken($shortAccessToken, $siteId, $secret) {
        $ch = curl_init();

        $params = [
            'client_secret' => App::parseEnv($secret),
            'grant_type' => 'ig_exchange_token',
            'access_token' => $shortAccessToken
        ];

        curl_setopt($ch, CURLOPT_URL,'https://graph.instagram.com/access_token?'.http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);

        $token = json_decode($res)->access_token;

        $plugin = Craft::$app->getPlugins()->getPlugin('craftagram');

        if ($plugin !== null) {
            $params = [
                'craftagramSiteId' => $siteId
            ];

            $longAccessTokenRecord = SettingsRecord::findOne($params);

            $longAccessTokenRecord->setAttribute('longAccessToken', $token);
            $longAccessTokenRecord->save();
        }

        return $token;
    }

    /**
     * Get instagram feed
     *
     * @return string|null
     */
    public function getInstagramFeed($limit, $siteId, $after) {

        if ($siteId == 0) {
            $siteId = Craft::$app->sites->primarySite->id;
        }

        $longAccessTokenRecord = Craftagram::$plugin->craftagramService->getLongAccessTokenSetting($siteId);

        if (!$longAccessTokenRecord) {
            return false;
        }

        $ch = curl_init();

        $params = [
            'fields' => 'caption,id,media_type,media_url,permalink,thumbnail_url,timestamp,username,children{media_type,media_url,thumbnail_url}',
            'access_token' => $longAccessTokenRecord,
            'limit' => $limit
        ];


        if ($after != '') {
            $params['after'] = $after;
        }

        curl_setopt($ch, CURLOPT_URL,'https://graph.instagram.com/me/media?'.http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($res);

        if (!isset($res->data)) {
            LogToFile::error('Failed to get data. Response from Instagram: ' . json_encode($res), 'craftagram');
        }

        return (isset($res->data) ? $res : null);
    }

    /**
     * Get instagram feed
     *
     * @return mixed
     */
    public function handleAuthentication()
    {
        list($username, $password) = Craft::$app->getRequest()->getAuthCredentials();

        if (!$username || !$password) {
            return false;
        }

        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail(Db::escapeParam($username));

        if (!$user) {
            return false;
        }

        if (!$user->authenticate($password)) {
            return false;
        }

        return true;
    }

    /**
     * Get profile information
     *
     * @return string|null
     */
    public function getProfileMeta($username) {
        try {

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,'https://www.instagram.com/'.$username.'/?__a=1');
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $res = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($res);

            $meta = null;

            if (isset($res->graphql)) {
                if (isset($res->graphql->user)) {
                    $meta = [
                        'profile_picture' => $res->graphql->user->profile_pic_url,
                        'profile_picture_hd' => $res->graphql->user->profile_pic_url_hd,
                        'followers' => $res->graphql->user->edge_followed_by->count,
                        'following' => $res->graphql->user->edge_follow->count,
                    ];
                }
            }

            return $meta;

        } catch (Exception $e) {
            LogToFile::error('Failed to get profile meta. This endpoint may no longer be available.', 'craftagram');
            return null;
        }
    }


}
