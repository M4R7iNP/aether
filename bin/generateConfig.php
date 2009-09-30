#!/usr/bin/php
<?php // vim:set ts=4 sw=4 et:

require_once("/home/lib/Autoload.php");
require_once("/home/lib/libDefines.lib.php");
require_once(AETHER_PATH . 'lib/AetherConfig.php');
require_once(AETHER_PATH . 'Aether.php');
require_once(LIB_PATH . 'Cache.lib.php');

/**
 * 
 * Generate compact configuration file with all import statements
 * included in final file for optimization.
 * Supports two options: 
 * Option 1:
 *      $mode which can be "test" or "prod"
 *      This states wether or not test-files or production-files (settings)
 *      will be used throughout the project
 * Option 2: nocache can be passed as the second argument wich removes cache 
 *      from the config files
 * Created: 2007-11-13
 * @author Raymond Julin
 */


/**
 * Find where the config/ is located. Should be ../config from here
 */
echo "\nThis script creates a corrected config file for Aether, and also";
echo "\nbuilds some cached data specific for this project based on the";
echo "\nconfig file for the project.\n\n";
$folder = preg_replace("/bin\/?/", "", getcwd());
if (!preg_match("/^\/.*?\/config\/?/", $folder))
    $folder .= "/config/";

// Strip double slashes
$configFolder = preg_replace("/\/{2}/", "/", $folder);

// Make sure a / is the last character
if (substr($configFolder, -1) != "/")
    $configFolder .= "/";

/**
 * What mode should configuration file be generated for
 */
$prefix = empty($argv[1]) ? 'prod' : $argv[1];
if (!in_array($prefix, array("prod", "test"))) {
    echo "Unknown target '{$argv[1]}'.  Must be 'prod' or 'test'. Aborting.\n";
    exit(-1);
}

/**
* Find base configuration file
 */
$baseConfig = $configFolder . "aether.config.xml";

// Verify that file exists before trying to parse it
if (!file_exists($baseConfig)) {
    if (!file_exists('aether.config.xml'))
        exit("Base config file doesnt exist [config/aether.config.xml].");
    else {
        $baseConfig = 'aether.config.xml';
        $configFolder = '';
    }
}

$doc = loadConfig($baseConfig);
$xpath = new DOMXPath($doc);
$xquery = "//import";
$nodelist = $xpath->query($xquery);

/**
 * Import all linked documents
 */
echo "Importing linked documents\n";
foreach ($nodelist as $node) {
    $toImport = $filename = $node->nodeValue; 
    $parent = $node->parentNode;
    if (strpos($toImport, "/") !== 0)
        $toImport = $configFolder . $toImport;
    // Prefixed file
    $prefixedFile = str_replace($filename, $prefix . "." . $filename, $toImport);
    // If prefixed file exists, use it
    if (file_exists($prefixedFile))
        $toImport = $prefixedFile;
    elseif ($prefix == 'test' AND !file_exists($toImport))
        $toImport = str_replace($filename, 'prod.' . $filename, $toImport);

    // Read in import file
    $import = loadConfig($toImport);

    foreach ($import->documentElement->childNodes as $child) {
        $import = $doc->importNode($child,true);
        $parent->insertBefore($import, $node);
    }
    $node->parentNode->removeChild($node);
}

// Add a little comment so people wont fuck this file up
$comment = "WARNING: This is an autogenerated configuration file " .
    "for the Aether framework. By changing anything here manualy " .
    "you get an award for \"stupid action of the day\".";
if ($prefix == "prod") {
    $comment .= "\nDANGER: This file is used in a production " .
        "setting, which leaves you absolutely no reason for changing ".
        "it. Use the generator provided";
}
else {
    $comment .= "\nTESTING: This file is used for testing " .
        "but editing is still a bad thing, stay away.";
}
$domComment = $doc->createComment($comment);
$doc->insertBefore($domComment, $doc->firstChild);

// Figure out where to save
$saveTo = $configFolder . "autogenerated.config.xml";
// Define a unique name for project config file for various use
$unique = str_replace('/', '_', $saveTo);

/**
 * Mark in the xml what environment we are running
 * First use XPath (very cool) to find each <site>
 * We will add an option for each site with this data
 */
echo "Setting generated options: AetherRunningMode, ModuleMap\n";
$xpath = new DOMXPath($doc);
$xquery = "/config/site";
$nodelist = $xpath->query($xquery);
foreach ($nodelist as $node) {
    $opt = $doc->createElement("option", $prefix);
    $opt->setAttribute("name", "AetherRunningMode");
    $node->insertBefore($opt, $node->firstChild);
    $modmapCache = 'module_map' . $unique;
    $modmapOpt = $doc->createElement("option", $modmapCache);
    $modmapOpt->setAttribute("name", "ModuleMap");
    $node->insertBefore($modmapOpt, $node->firstChild);
}


/**
 * Wrap things up by saving the generated configuration to
 * $config/autogenerated.config.xml
 */
echo "Saving config file [$saveTo] moving old to [{$saveTo}.old]\n";
copy($saveTo, $saveTo . ".old");
$doc->save($saveTo);


/**
 * Generate an array over all modules used for project
 * and the stages they operate in under.
 * This is used to quickly check if a module needs to be
 * included at stage start, run or stop
 */
echo "Building array over used modules\n";
$config = new AetherConfig($saveTo);
$modules = $config->listUsedModules();
/**
 * Spit out warning about non found modules
 * Todo: Doesnt actually disable the modules as it should
 * 
 */
if (count($modules['missing']) > 0) {
    echo "\n===== WARNINGS =====\n";
    echo "Missing modules [searchpath: \"" . 
            join("\", \"", $modules['searchPath']) . "\"]\n";
    foreach ($modules['missing'] as $missing) {
        echo "\033[1;31mCould not locate source file for required module [$missing]\n";
    }
    echo "\033[0m===== END WARNINGS =====\n\n";
    unset($modules['missing']);
}


echo "Module map written to cachename: $modmapCache\n";
$cache = new Cache(false, true, true);
$cache->saveObject($modmapCache, $modules);
echo "Done\n\n";

/**
 * Loads an XML config file.
 * If "nocache" is specified as the second command argument the cache parameter
 * will be removed from the config files
 * 
 * @param mixed $file
 * @access private
 * @return Object DOMDocument object
 */
function loadConfig($file) {
    global $argv;
    $import = new DOMDocument;
    $import->preserveWhiteSpace = false;

    if (isset($argv[2]) && $argv[2] == 'nocache') {
        $file = file_get_contents($file);
        $file = preg_replace('/cache="[0-9]*"/', '', $file);
        $import->loadXML($file);
    } else {
        $import->load($file);
    }

    return $import;
}
?>
