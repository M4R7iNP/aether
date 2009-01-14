<?php // vim:set ts=4 sw=4 et:

/**
 * Base class for command line scripting in Aether
 * Sorts out command line option handling for you
 * as this can be a bit cumbersome to do manually each time
 * 
 * Created: 2008-12-12
 * @author Raymond Julin
 * @package aether
 */

class AetherCLI {
    
    /**
     * Define legal options for this program.
     * All other passed options will be ignored
     * Options are supplied on this form:
     * array('shortName' => 'longName');
     * Example:
     * array('a' => 'action', 'p' => 'path');
     * @var array
     */
    protected $allowedOptions = array();
    
    /**
     * Hold all parsed options
     * @var array
     */
    protected $options = array();
    
    /**
     * Handle option parsing
     *
     * @access 
     * @return 
     */
    public function __construct() {
        $this->mixinHelpSupport();
        $this->options = $this->parseOptions($_SERVER['argv']);
        // Run help file if help required
        if (($this->hasOptions(array('help')) AND count($this->options) == 1)
            OR count($this->options) == 0) {
            $this->displayHelpFile();
        }
    }
    
    /**
     * Mix in support for --help/-h always
     * And let it act upon this option without using
     * the extending script
     *
     * @access protected
     * @return void
     */
    protected function mixinHelpSupport() {
        if (!array_key_exists('h', $this->allowedOptions))
            $this->allowedOptions['h'] = 'help';
    }
    
    /**
     * Display the help file following a cli app
     *
     * @access protected
     * @return void
     */
    protected function displayHelpFile() {
        $dir = dirname(__FILE__);
        $file = substr_replace(__FILE__, 'help', -3);
        if (file_exists($file)) {
            $content = file_get_contents($file);
        }
        else {
            // Use default help file for AetherCLI
            $content = file_get_contents(AETHER_PATH . 'lib/AetherCLI.help');
        }
        echo $content;
    }
    
    /**
     * Parse options from command line
     * Only defined options from allowedOptions will be taken into
     * consideration. All others supplied info will be overlooked
     *
     * @access private
     * @return array
     */
    protected function parseOptions($args) {
        $options = array();
        if (is_array($args) AND count($args) > 0) {
            foreach ($args as $arg) {
                // Is valid option
                if (preg_match('/(--[a-z]+|-[a-z]{1})(?>=[a-z-_\/]+)?/', $arg))  {
                    $parts = explode('=', $arg);
                    $name = preg_replace('/[-]{1,2}/', '', $parts[0]);

                    // Always use long name in returned array
                    if (strlen($name) == 1)
                        $name = $this->allowedOptions[$name];

                    // Only use allowed options
                    if (in_array($name, $this->allowedOptions))
                        $options[$name] = $parts[1];
                }
            }
        }
        return $options;
    }
    
    /**
     * Return a single options value by name
     *
     * @access protected
     * @return mixed
     * @param string $key
     */
    public function getOption($key) {
        if (array_key_exists($key, $this->options))
            return $this->options[$key];
    }
    
    /**
     * Verify CLI job has all options
     *
     * @access protected
     * @return boolean
     * @param array $opts As long opts
     */
    public function hasOptions($opts) {
        foreach ($opts as $o) {
            if (!array_key_exists($o, $this->options))
                return false;
        }
        return true;
    }
}
?>
