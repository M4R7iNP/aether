<?php // 
/**
 * 
 * Facade over Smarty templating engine
 * 
 * Created: 2009-04-23
 * @author Raymond Julin
 * @package aether
 */

class AetherTemplateSmarty extends AetherTemplate {
    
    /**
     * Construct
     *
     * @param AetherServiceLocator $sl
     */
    public function __construct(AetherServiceLocator $sl) {
        $this->engine = new Smarty;
        $this->sl = $sl;
        $options = $this->sl->get('aetherConfig')->getOptions();

        $base = $this->sl->get('projectRoot') . 'templates/';
        // Add project root first in template search path
        $templateDirs[] =  $base;
        if (isset($options['searchpath'])) {
            $search = array_map("trim", explode(";", $options['searchpath']));
            foreach ($search as $dir) {
                $templateDirs[] = $dir . "templates/";
            }
        }
        $this->engine->error_reporting = E_ALL ^ E_NOTICE;
        $this->engine->template_dir = $templateDirs;
        $this->engine->compile_dir = $base . 'compiled/';
        $this->engine->config_dir = $base . 'configs/';
        $this->engine->cache_dir = $base . 'cache/';
    }

    /**
     * Set a template variable 
     *
     * @return void
     * @param string $key
     * @param mixed $value
     */
    public function set($key,$value) {
        $this->engine->assign($key,$value);
    }

    /**
     * Fetch rendered template
     *
     * @return string
     * @param string $name
     */
    public function fetch($name) {
        return $this->engine->fetch($name);
    }
}
