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

use Craft;
use scaramangagency\craftagram\Craftagram;

use craft\console\Controller;
use yii\console\ExitCode;


class TokenController extends Controller {

    // Public Methods
    // =========================================================================

    /**
     * Refreshes Instagram Token.
     */
    public function actionIndex(?int $siteId = null)
    {
        if (!$siteId) {
            $siteId = (int) Craft::$app->getSites()->getPrimarySite()->id;
        }

        $result = Craftagram::$plugin->craftagramService->refreshToken($siteId);

        if (true === $result) {
            return ExitCode::OK;
        }

        return ExitCode::UNSPECIFIED_ERROR;
    }
}
