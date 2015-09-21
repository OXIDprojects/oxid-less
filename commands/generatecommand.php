<?php
/**
 * generatecommand.php
 *
 * @version   GIT: $Id$ PHP5.4 (16.10.2014)
 * @author    Robin Lehrmann <info@renzel-agentur.de>
 * @copyright Copyright (C) 22.10.2014 renzel.agentur GmbH. All rights reserved.
 * @license   http://www.renzel-agentur.de/licenses/raoxid-1.0.txt
 * @link      http://www.renzel-agentur.de/
 *
 */
class GenerateCommand extends oxConsoleCommand
{

    /**
     * configure
     */
    public function configure()
    {
        $this->setName('less:generate');
        $this->setDescription('Compiles css');
    }

    /**
     * help
     *
     * @param oxIOutput $oOutput output
     */
    public function help(oxIOutput $oOutput)
    {
        $oOutput->writeLn('usage: less:generate');
    }

    /**
     * get less files from template
     *
     * @param string $tpl template to parse
     *
     * @return array
     */
    private function _parseForLess($tpl)
    {
        $content = file_get_contents($tpl);
        if ($pos = strpos($content, 'lessload')) {
            preg_match_all('/\'*([a-zA-Z0-9\/\_\-]+.less)/', $content, $results);
            if (is_array($results)) {
                return $results;
            }
        }
        return array();
    }

    /**
     * get files by extension
     *
     * @param string $extension extension e.g. tpl,less,css,js
     *
     * @return array
     */
    private function _getFilesByExtension($extension)
    {
        $files = array();
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(OX_BASE_PATH)) as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                $files[] = $file->getRealPath();
            }
        }
        return $files;
    }

    /**
     * get url
     *
     * @return string
     */
    private function _getUrl()
    {
        $cdnUrl = oxRegistry::getConfig()->getShopConfVar('sCDNUrl', 1, 'module:raless');
        if ($cdnUrl) {
            return rtrim($cdnUrl, '/') . '/';
        }
        return;
    }

    /**
     * get module url by less file
     *
     * @param string $file less file of module
     *
     * @return string
     */
    private function _getModuleUrlByFile($file)
    {
        $oModule = oxNew('oxModule');
        $sModulePath = str_replace(OX_BASE_PATH . 'modules/', '', $file);
        $aExplodedModulePath = explode('/', $sModulePath);
        if (!$oModule->loadByDir($aExplodedModulePath[0] . '/' . $aExplodedModulePath[1])) {
            $oModule->loadByDir($aExplodedModulePath[0]);
        }
        return $this->_getUrl() . 'modules/' . $oModule->getModulePath() . '/';
    }

    /**
     * scan templates for less include
     *
     * @param oxIOutput $oOutput output
     */
    public function execute(oxIOutput $oOutput)
    {
        $sGenDir = oxRegistry::getConfig()->getOutDir() . 'gen/';
        if (is_dir($sGenDir)) {
            mkdir($sGenDir);
        }
        $lessFiles = array();
        $options = array(
            'compress' => true,
            'cache_method' => 'serialize',
            'cache_dir' => '/' . trim(oxRegistry::get("oxConfigFile")->getVar("sCompileDir"), '/') . '/less'
        );
        foreach ($this->_getFilesByExtension('tpl') as $file) {
            foreach ($this->_parseForLess($file) as $less) {
                $lessFiles = array_merge($lessFiles, $less);
            }
        }
        foreach ($this->_getFilesByExtension('less') as $less) {
            foreach ($lessFiles as $lessFile) {
                if (strpos($less, $lessFile)) {
                    $sCssFile = md5(realpath($less)) . '.css';
                    if (strpos($less, 'module')) {
                        $path = $this->_getModuleUrlByFile($less);
                    } else {
                        $path = $this->_getUrl();
                    }
                    $oOutput->writeLn('generate: ' . $less);
                    copy(oxRegistry::get("oxConfigFile")->getVar("sCompileDir") . 'less/' . Less_Cache::Get(array($less => $path), $options), $sGenDir . $sCssFile);
                }
            }
        }
        $oOutput->writeLn('clear less cache...');
        foreach (new DirectoryIterator($options['cache_dir']) as $file) {
            if ($file->isFile()) {
                unlink($file->getRealPath());
            }
        }
        rmdir($options['cache_dir']);
    }
}
