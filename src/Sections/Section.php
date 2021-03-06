<?php

namespace Aether\Sections;

use Throwable;
use Aether\Aether;
use Aether\Response\Text;
use Aether\Modules\Module;
use Aether\Modules\ModuleFactory;
use Aether\Exceptions\ConfigError;
use Aether\Exceptions\ServiceNotFound;

/**
 * Base class definition of aether sections
 *
 * Created: 2007-02-05
 * @author Raymond Julin
 * @package aether.lib
 */
abstract class Section
{
    /**
     * The Aether instance.
     *
     * @var \Aether\Aether
     */
    protected $aether;

    /**
     * Legacy alias for the $aether property.
     *
     * @var \Aether\Aether
     */
    protected $sl;

    /**
     * Cache time for varnish
     *
     * @var integerino
     */
    protected $pageCacheTime;

    public function __construct(Aether $aether)
    {
        $this->aether = $aether;
        $this->sl = $this->aether;
    }

    private function preloadModules($modules, $options)
    {
        // Preload modules, set cachetime and find minimum page cache time
        foreach ($modules as &$module) {
            if (!isset($module['options'])) {
                $module['options'] = array();
            }
            $object = "";
            // Get module object
            try {
                $object = ModuleFactory::create(
                    $module['name'],
                        $this->aether,
                    $module['options'] + $options
                );

                // If the module, in this setting, blocks caching, accept
                if ($this->cache && ($cachetime = $object->getCacheTime()) !== null) {
                    $module['cache'] = $cachetime;

                    // Reset page cache time to module since we ask for stuff
                    // to be updated at an earlier interval
                    $this->pageCacheTime = min($this->pageCacheTime, $module['cache']);
                }

                $module['obj'] = $object;
            } catch (Throwable $e) {
                $this->handleModuleError($e);
            }
        }

        return $modules;
    }

    private function loadModule($module)
    {
        if ($this->cache && array_key_exists('cache', $module) && $module['cache'] > 0) {
            $mCacheName = $this->cacheName . $module['name'] ;

            if (isset($module['provides'])) {
                $mCacheName .= $module['provides'];
            }

            if (array_key_exists('cacheas', $module)) {
                $mCacheName = $url->get('host') . $module['cacheas'];
            }

            // Try to read from cache, else generate and cache
            if (($mOut = $this->cache->get($mCacheName)) == false) {
                if (isset($module['obj'])) {
                    $mod = $module['obj'];
                    $mCacheTime = $module['cache'];

                    try {
                        $mOut = $mod->run();
                        if (is_numeric($mCacheTime) && $mCacheTime > 0) {
                            $this->cache->set($mCacheName, $mOut, $mCacheTime);
                        } else {
                            // uncacbleable page if at least one module is uncacheable
                            $this->pageCacheTime = 0;
                        }
                    } catch (Throwable $e) {
                        $this->handleModuleError($e);
                    }
                }
            }
        } else {
            // Module shouldn't be cached, just render it without
            // saving to cache
            if (isset($module['obj'])) {
                $mod = $module['obj'];

                try {
                    $mOut = $mod->run();
                } catch (Throwable $e) {
                    $this->handleModuleError($e);
                    return false;
                }
            }
        }

        $module['output'] = $mOut;

        return $module;
    }

    /**
     * Render content from modules
     * this is where caching is implemented
     * TODO Possible refactoring, many leves of nesting
     * TODO Reconsider the solution with passing in extra tpl data
     * to renderModules() as an argument. Smells bad
     *
     * @access protected
     * @return string
     */
    protected function renderModules()
    {
        if ($this->aether->bound('timer')) {
            $timer = $this->aether['timer'];

            $timer->start('module_run');
        }

        $config = $this->aether['aetherConfig'];
        $this->cache = $this->aether->bound('cache') ? $this->aether['cache'] : false;
        $this->pageCacheTime = $config->getCacheTime();

        /**
         * Decide cache name for rule based cache
         * If the option cacheas is set, we will use the cache name
         * $domainname_$cacheas
         */
        $url = $this->aether['parsedUrl'];
        if ($this->cache) {
            $cacheas = $config->getCacheName();
            if ($cacheas != false) {
                $this->cacheName = $url->get('host') . '_' . $cacheas;
            } else {
                $this->cacheName = $url->cacheName();
            }
        }

        /**
         * If one object requests no cache of this request
         * then we need to take that into consideration.
         * If the application frontend and adminpanel lives
         * at the same URL, its crucial that the admin part is
         * not cached and later on displayed to an end user
         */
        $options = $config->getOptions();

        $modules = $this->preloadModules($config->getModules(), $options);

        /**
         * If we have a timer, end this timing
         * we're in test mode and thus showing timing
         * information
         */
        if (isset($timer) and is_object($timer)) {
            $timer->tick('module_run', 'read_config');
        }

        /**
         * Render page
         */

        /* Load controller template
         * This template knows where all modules should be placed
         * and have internal wrapping html for this section
         */
        $tplInfo = $config->getTemplate();
        $tpl = $this->aether->getTemplate();
        if (is_array($modules)) {
            foreach ($modules as &$module) {
                // If module should be cached, handle it
                $module = $this->loadModule($module);
                if (!$module) {
                    continue;
                }

                /**
                 * Support multiple modules of same type by
                 * specificaly naming them with a surname when
                 * duplicates are encountered
                 */
                $modId = isset($module['provides']) ? $module['provides'] : $module['name'];

                $this->provide($modId, $module['output']);
                // DEPRECATED: direct access to $ModuleName in template
                $tpl->set($module['name'], $module['output']);

                /**
                 * If we have a timer, end this timing
                 * we're in test mode and thus showing timing
                 * information
                 */
                if (isset($timer) and is_object($timer)) {
                    $timer->tick('module_run', $modId);
                }
            }
        }

        if (empty($tplInfo['name'])) {
            throw new ConfigError("Template not specified for url: " . (string)$url);
        }

        $output = $tpl->fetch($tplInfo['name']);

        // Varnish cache header
        if (is_numeric($this->pageCacheTime) && !headers_sent()) {
            header("Cache-Control: s-maxage={$this->pageCacheTime}");
        }

        /**
         * If we have a timer, end this timing
         * we're in test mode and thus showing timing
         * information
         */
        if (isset($timer) and is_object($timer)) {
            $timer->end('module_run');
        }
        // Return output
        return $output;
    }

    /**
     * Render this section
     * Returns a Response object which can contain a text response or
     * a header redirect response
     * The advantages to using response objects is to more cleanly
     * supporting header() redirects. In other words; more response
     * types
     *
     * @access public
     * @return AetherResponse
     */
    abstract public function response();

    /**
     * Render service
     *
     * @access public
     * @return AetherResponse
     * @param string $moduleName
     * @param string $serviceName Name of service
     */
    public function service($name, $serviceName)
    {
        // Locate module containing service
        $config = $this->aether['aetherConfig'];
        $options = $config->getOptions();

        // Create module
        $mod = null;
        $configModules = $config->getModules();
        $configModuleNames = array_map(function ($mod) {
            return $mod['name'];
        }, $configModules);

        if (isset($configModules[$name])) {
            $module = $configModules[$name];
        } elseif (in_array($name, $configModuleNames)) {
            foreach ($configModules as $m) {
                if ($m['name'] == $name) {
                    $module = $m;
                    break;
                }
            }
        } else {
            $module = array('name' => $name);
        }

        if (!isset($module['options'])) {
            $module['options'] = array();
        }
        $opts = $module['options'] + $options;
        if (isset($opts['session']) && $opts['session'] == 'on') {
            session_start();
        }

        // Get module object
        $mod = ModuleFactory::create($module['name'], $this->aether, $opts);

        if (! $mod instanceof Module) {
            throw new ServiceNotFound("Service run error: Failed to locate module [$name], check if it is loaded in config for this url: " . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . (isset($_SERVER['HTTP_REFERER']) ? ", called from URI: " . $_SERVER['HTTP_REFERER'] : ""));
        }

        return $mod->service($serviceName);
    }

    /**
     * Provide the output of a module
     *
     * @return void
     * @param string $name
     * @param string $content
     */
    private function provide($name, $content)
    {
        $vector = $this->aether->getVector('aetherProviders');
        $vector[$name] = $content;
    }

    /**
     * Handle exceptions thrown by modules. In production, trigger_error() is
     * used, otherwise the exception will be re-thrown.
     *
     * @param  \Throwable  $e
     * @return void
     * @throws \Throwable
     */
    private function handleModuleError($e)
    {
        if (config('app.env') !== 'production') {
            throw $e;
        }

        trigger_error("Caught exception at " . $e->getFile() . ":" . $e->getLine() . ": " . $e->getMessage() . ", trace: " . str_replace("\n", ", ", $e->getTraceAsString()));
    }

    protected function triggerDefaultRule()
    {
        $config = $this->aether['aetherConfig'];
        $config->reloadConfigFromDefaultRule();
        $section = SectionFactory::create(
            $config->getSection(),
            $this->aether
        );
        return $section->response();
    }
}
