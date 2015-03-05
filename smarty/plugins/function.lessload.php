<?php
/**
 * function.lessload.php
 *
 * @version   GIT: $Id$ PHP5.4 (16.10.2014)
 * @author    Robin Lehrmann <info@renzel-agentur.de>
 * @copyright Copyright (C) 22.10.2014 renzel.agentur GmbH. All rights reserved.
 * @license   MIT
 * @link      http://www.renzel-agentur.de/
 */

/**
 * less load smarty plugin
 *
 * @param array $params params
 * @param mixed $smarty Smarty object
 *
 * @return string
 */
function smarty_function_lessload($params, $smarty)
{
    $myConfig = oxRegistry::getConfig();
    $sShopUrl = oxRegistry::getConfig()->getShopUrl();

    if ($params['include']) {
        $sStyle = $params['include'];
        $sLessFile = $sStyle;

        if (!preg_match('#^http?://#', $sStyle)) {
            $sLessFile = str_replace($sShopUrl, OX_BASE_PATH, $sLessFile);
        }

        /* @var $oActiveTheme \oxTheme */
        $oActiveTheme = oxNew('oxTheme');
        $oActiveTheme->load($oActiveTheme->getActiveThemeId());
        $iShop = $myConfig->getShopId();

        do {
            $sLessPathNFile = $myConfig->getDir($sLessFile, 'src/less', $myConfig->isAdmin(), oxRegistry::getLang()->getBaseLanguage(), $iShop, $oActiveTheme->getId());
            $oActiveTheme = $oActiveTheme->getParent();
        } while (!is_null($oActiveTheme) && !file_exists($sLessPathNFile));

        $sLessFile = $sLessPathNFile;

        // File not found ?
        if (!$sLessFile) {
            if ($myConfig->getConfigParam('iDebug') != 0) {
                $sError = "{lessload} resource not found: " . htmlspecialchars($params['include']);
                trigger_error($sError, E_USER_WARNING);
            }
            return;
        } else {
            $sCssUrl = compile($sShopUrl, $sLessFile, $myConfig);
        }
    }

    $params['include'] = $sCssUrl;
    if ($params['blNotUseOxStyle']) {
        return '<link rel="stylesheet" type="text/css" href="'.$sCssUrl.'" />'.PHP_EOL;
    } else {
        return smarty_function_oxstyle($params, $smarty);
    }
}

/**
 * get config param of active theme
 *
 * @param string $sKey var name
 *
 * @return mixed
 */
function getThemeConfigVar($sKey)
{
    /** @var \oxTheme $oTheme */
    $oTheme = oxNew('oxTheme');
    return oxRegistry::getConfig()->getShopConfVar($sKey, null, 'theme:' . $oTheme->getActiveThemeId());
}

/**
 * compile less file
 *
 * @param string $sShopUrl  shop url
 * @param string $sLessFile less file
 *
 * @return string
 */
function compile($sShopUrl, $sLessFile)
{
    $myConfig = oxRegistry::getConfig();
    $sFilename = str_replace('/', '_', str_replace($sShopUrl, '', $sLessFile));
    $sFilename = md5($sFilename) . '.css';

    $sGenDir = $myConfig->getOutDir() . 'gen/';
    if (!is_dir($sGenDir)) {
        mkdir($sGenDir);
    }

    $parser = new Less_Parser(
        array(
            'compress'     => $myConfig->isProductiveMode(),
            'cache_method' => 'serialize',
            'cache_dir'    => oxRegistry::get("oxConfigFile")->getVar("sCompileDir") . 'less'
        )
    );
    /** @var \oxTheme $oTheme */
    $oTheme = oxNew('oxTheme');

    try {
        $parser->parseFile($sLessFile, $sShopUrl . $myConfig->getOutDir(false) . $oTheme->getActiveThemeId() . '/src/');

        foreach (explode(',', trim($myConfig->getShopConfVar('sVariables', null, 'module:raless'))) as $sVar) {
            if (!is_null(getThemeConfigVar($sVar)) && getThemeConfigVar($sVar) !== '') {
                $parser->ModifyVars(array($sVar => getThemeConfigVar($sVar)));
            }
        }

        $sCssFile = $sGenDir . $sFilename;
        $sCssFile = str_replace('.less', '.css', $sCssFile);
        $sCssUrl = str_replace($myConfig->getOutDir(), $myConfig->getCurrentShopUrl() . 'out/', $sCssFile);
        file_put_contents($sCssFile, $parser->getCss());

        return $sCssUrl;
    } catch (Exception $e) {
        if ($myConfig->getConfigParam('iDebug') != 0) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
    }
    return null;
}
