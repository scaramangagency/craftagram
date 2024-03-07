<?php
/**
 * craftagram plugin for Craft CMS 3.x
 *
 * Grab Instagram content through the Instagram Basic Display API
 *
 * @link      https://scaramanga.agency
 * @copyright Copyright (c) 2020 Scaramanga Agency
 */

namespace scaramangagency\craftagram\console\controllers;

use scaramangagency\craftagram\Craftagram;

use craft\console\Controller;
use yii\console\ExitCode;

class TokenController extends Controller {

    // Public Methods
    // =========================================================================

    /**
     * Refreshes Instagram long access token(s).
     *
     * @param  string|null $siteId if a siteId is given, update this site only
     * @return ExitCode 0 = OK, 1 = failed to refesh tokens for one or more sites
     */
    public function actionIndex(?int $siteId = null)
    {
        if ($siteId) {
            $sucess = Craftagram::$plugin->craftagramService->refreshTokenForSiteId($siteId);
        } else {
            $sucess = Craftagram::$plugin->craftagramService->refreshToken();
        }

        return $sucess
            ? ExitCode::OK
            : ExitCode::UNSPECIFIED_ERROR;
    }
}
