<?php
/*
HARDWARE.NO EDITORSETTINGS:
vim:set tabstop=4:
vim:set shiftwidth=4:
vim:set smarttab:
vim:set expandtab:
*/

require_once('/home/lib/libDefines.lib.php');
require_once(LIB_PATH . 'Cache.lib.php');
require_once(LIB_PATH . 'SessionHandler.lib.php');
require_once(LIB_PATH . 'time/TimeFactory.lib.php');
require_once(AETHER_PATH . 'lib/AetherExceptions.php');
require_once(AETHER_PATH . 'lib/AetherServiceLocator.php');
require_once(AETHER_PATH . 'lib/AetherUrlParser.php');
require_once(AETHER_PATH . 'lib/AetherConfig.php');
require_once(AETHER_PATH . 'lib/AetherSectionFactory.php');
require_once(AETHER_PATH . 'lib/AetherSection.php');
require_once(AETHER_PATH . 'lib/AetherActionResponse.php');
require_once(AETHER_PATH . 'lib/AetherTextResponse.php');
require_once(AETHER_PATH . 'lib/AetherXMLResponse.php');
require_once(AETHER_PATH . 'lib/AetherJSONResponse.php');
require_once(AETHER_PATH . 'lib/AetherJSONCommentFilteredResponse.php');
require_once(AETHER_PATH . 'lib/AetherModule.php');
require_once(AETHER_PATH . 'lib/AetherModuleHeader.php');
require_once(AETHER_PATH . 'lib/AetherModuleFactory.php');
require_once(AETHER_PATH . 'lib/AetherModuleManager.php');
// Default to only smarty support for now
require_once(AETHER_PATH . 'lib/templating/smarty/libs/Smarty.class.php');

/**
 * 
 * Main class for Aether.
 * Fires up the Aether system and delegates down to
 * section that is requested based on the
 * rules.
 * 
 * Created: 2007-01-31
 * @author Raymond Julin
 * @package aether
 */

class Aether {
    
    /**
     * Hold service locator
     * @var AetherServiceLocator
     */
    private $sl = null;
    
    /**
     * Section
     * @var AetherSection
     */
    private $section = null;
    
    /**
     * Root folder for this project
     * @var string
     */
    private $projectRoot;
    
    /**
     * Module manager
     * @var AetherModuleManager
     */
    private $moduleManager;
    
    /**
     * Constructor. 
     * Parses url, prepares everything
     *
     * @access public
     * @return Aether
     * @param string $configPath
     */
    public function __construct($configPath=false) {
        $this->sl = new AetherServiceLocator;
        // Initiate all required helper objects
        $parsedUrl = new AetherUrlParser;
        $parsedUrl->parseServerArray($_SERVER);
        $this->sl->set('parsedUrl', $parsedUrl);
        
        // Set autoloader
        spl_autoload_register(array('Aether', 'autoLoad'));
        
        /**
         * Find config folder for project
         * By convention the config folder is always placed at
         * $project/config, while using getcwd() MUST return the
         * $project/www/ folder
         */
        $projectPath = getcwd();
        // Replace "www" with "config" and add trailling slash
        $projectPath = preg_replace("/www\/?/", "", $projectPath);
        $this->projectRoot = $projectPath;
        $this->sl->set('projectRoot', $this->projectRoot);
        $configPath = $projectPath . "config/autogenerated.config.xml";
        if ($configPath === false) {
            $thisDir = getcwd();
            if (file_exists($thisDir . '/aether.config.xml'))
                $configPath = $thisDir . '/aether.config.xml';
            else
                $configPath = AETHER_PATH . 'aether.config.xml';
        }
        try {
            $config = new AetherConfig($configPath);
            $config->matchUrl($parsedUrl);
            $this->sl->set('aetherConfig', $config);
        }
        catch (AetherMissingFileException $e) {
            /**
             * This means that someone forgot to ensure the config
             * file actually exists
             */
            $msg = "No configuration file for project found: " . $e->getMessage();
            throw new Exception($msg);
        }
        catch (AetherNoUrlRuleMatchException $e) {
            /**
             * This means parsing of configuration file failed
             * by the simple fact that no rules matches
             * the url. This is due to a bad developer
             */
            $msg = "No rule matched url in config file: " . $e->getMessage();
            throw new Exception($msg);
        }
        /**
         * Set up module manager and run the start() stage
         */
        $this->moduleManager = new AetherModuleManager($this->sl);
        $this->moduleManager->start();

        $options = $config->getOptions();

        /**
         * Make sure base and root for this request is stored
         * in the service locator so it can be made available
         * to the magical $aether array in templates
         */
        $magic = $this->sl->getVector('templateGlobals');
        $magic['base'] = $config->getBase();
        $magic['root'] = $config->getRoot();
        $magic['urlVars'] = $config->getUrlVars();
        $magic['runningMode'] = $options['AetherRunningMode'];
        $magic['requestUri'] = $_SERVER['REQUEST_URI'];
        $magic['domain'] = $_SERVER['SERVER_NAME'];
        if (isset($_SERVER['HTTP_REFERER']))
            $magic['referer'] = $_SERVER['HTTP_REFERER'];
        if ($_SERVER['SERVER_PORT'] != 80)
            $magic['domain'] .= ":" . $_SERVER['SERVER_PORT'];
        $magic['options'] = $options;

        /**
         * If we are in TEST mode we should prepare a timer object
         * and time everything that happens
         */
        if ($options['AetherRunningMode'] == 'test') {
            // Prepare timer
            $timer = TimeFactory::create('norwegian');
            $timer->timerStart('aether_main');
            $this->sl->set('timer', $timer);
        }
        /**
         * Start session if session switch is turned on in 
         * configuration file
         */
        if (array_key_exists('session', $options) AND $options['session'] == 'on')
            session_start();

        // Initiate section
        try {
            $searchPath = (isset($options['searchpath'])) 
                ? $options['searchpath'] : AETHER_PATH;
            AetherSectionFactory::$path = $searchPath;
            $this->section = AetherSectionFactory::create(
                $config->getSection(), 
                $this->sl
            );
            $this->sl->set('section', $this->section);
            if (isset($timer)) 
                $timer->timerTick('aether_main', 'section_initiate');
        }
        catch (Exception $e) {
            // Failed to load section, what to do?
            throw new Exception('Failed horribly: ' . $e->getMessage());
        }
    }
    
    /**
     * Render aether system
     * Initialization point. When render() is called
     * everything in the chain of actions is performed one by one
     * untill we have a response to serve the user
     *
     * @access public
     * @return string
     */
    public function render() {
        /**
         * If a service is requested simply render the service
         */
        if (isset($_GET['service']) AND isset($_GET['module'])) {
            $response = $this->section->service(
                $_GET['module'], $_GET['service']);
            $response->draw($this->sl);
        }
        else {
            $response = $this->section->response();
            $response->draw($this->sl);
            /**
             * Run stop stage of modules
             */
            $this->moduleManager->stop();
        }
    }

    /**
     * Used to autoload aether classes
     *
     * @access public
     * @return bool
     */
    public static function autoLoad($class) {
        if (class_exists($class, false))
            return true;

        // Split up the name of the class by camel case (AetherDriver
        $matches = preg_split('/([A-Z][^A-Z]+)/', $class, -1,
                              PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        // We should only autoload aether classes
        if (empty($matches) || $matches[0] != 'Aether')
            return false;

        // Find the class location
        switch ($matches[1]) {
            case 'Module':
                break;
            case 'Section':
                break;
            case 'Database':
                $path = AETHER_PATH . 'lib/';
                break;
            case 'ORM':
                $path = AETHER_PATH . 'lib/ORM';
                break;
            case 'Driver':
                $path = AETHER_PATH . 'lib/drivers';
                break;
            default:
                $path = AETHER_PATH . 'lib/';
                break;
        }


        $i = 0;
        foreach ($matches as $match) {
            // Turn the rest of the array into a string that can be used as a filename
            $filenameArray = array_slice($matches, $i);
            $filename = implode('', $filenameArray);

            // Check if there is a file with this name.
            // Files have precendence over folders
            $filename = $path . $filename . '.php';
            if (file_exists($filename)) {
                $filePath = $filename;
                break;
            }

            // If there is a directory with this name add it to the dir path
            $match = strtolower($match);
            if (file_exists($path . $match))
                $path = $path . $match . '/';
            else
                break;

            $i++;
        }

        if (isset($filePath) && !empty($filePath)) {
            require $filePath;
            return true;
        }
        
        return false;
    }
}
?>
