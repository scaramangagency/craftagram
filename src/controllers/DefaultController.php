<?php
/**
 * craftagram plugin for Craft CMS 3.x
 *
 * Grab Instagram content through the Instagram Basic Display API
 *
 * @link      https://scaramanga.agency
 * @copyright Copyright (c) 2020 Scaramanga Agency
 */

namespace scaramangagency\craftagram\controllers;

use craft\helpers\App;
use scaramangagency\craftagram\Craftagram;

use Craft;
use craft\web\Controller;
use craft\helpers\UrlHelper;
use yii\web\UnauthorizedHttpException;

/**
 * @author    Scaramanga Agency
 * @package   Craftagram
 * @since     1.0.0
 */
class DefaultController extends Controller {

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['refresh-token', 'auth', 'get-next-page', 'api'];

    // Public Methods
    // =========================================================================

    /**
     * Refresh the instagram token
     *
     * @return bool
     */
    public function actionRefreshToken() {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        return Craftagram::$plugin
            ->craftagramService
            ->refreshToken(
                (int) $siteId
            );
    }

    /**
     * Redirect user to instagram for authentication
     *
     * @return Response
     */
    public function actionHandleAuth($site_id, $client_id) {
        $url = rtrim(App::parseEnv(Craft::$app->sites->primarySite->baseUrl), '/');
        $appId = App::parseEnv($client_id);

        Craft::$app->getResponse()->redirect('https://api.instagram.com/oauth/authorize?client_id='.$appId.'&scope=user_profile,user_media&response_type=code&redirect_uri='.$url.'/actions/craftagram/default/auth&state='.$site_id)->send();
        exit;
    }

    /**
     * Redirect user to craftagram settings page
     *
     * @return Response|null
     */
    public function actionAuth() {
        $url = parse_url(rtrim(App::parseEnv(Craft::$app->sites->primarySite->baseUrl), '/') . $_SERVER['REQUEST_URI']);
        parse_str($url['query'], $params);
        $code = $params['code'];
        $siteId = $params['state'];

        if ($code != '') {
            $getToken = Craftagram::$plugin->craftagramService->getShortAccessToken($code, $siteId);
            Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('craftagram/settings/' . $siteId))->send();
            exit;
        }
    }

    /**
     * Return instagram feed based on supplied page
     *
     * @return string
     */
    public function actionGetNextPage($url, $siteId, $limit = 25) {
        $url = parse_url($url);

        if (array_key_exists('query', $url)) {
            parse_str($url['query'], $params);
            $after = $params['after'];
            $limit = $params['limit'];
        } else {
            $after = $url['path'];
        }

        return json_encode(Craftagram::$plugin->craftagramService->getInstagramFeed($limit, $siteId, $after));
    }

    /**
     * Return instagram feed based on supplied page
     *
     * @return string
     */
    public function actionApi($limit = 25, $siteId = 0, $url = '') {
        $isSecured = Craftagram::$plugin->craftagramService->checkIfSecured($siteId);

        if ($isSecured) {
            if (!Craftagram::$plugin->craftagramService->handleAuthentication()) {
                throw new UnauthorizedHttpException('Your request was made with invalid credentials.');
            }
        }

        header('Content-type:application/json;charset=utf-8');
        echo json_encode(Craftagram::$plugin->craftagramService->getInstagramFeed($limit, $siteId, $url));
        die();
    }
}
