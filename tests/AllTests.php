<?php // vim:set ts=4 sw=4 et:

require_once('/home/lib/libDefines.lib.php');
require_once('PHPUnit/Framework.php');
require_once(AETHER_PATH . 'tests/CLITest.php');
require_once(AETHER_PATH . 'tests/ConfigTest.php');
require_once(AETHER_PATH . 'tests/JsonCommentFilteredResponseTest.php');
require_once(AETHER_PATH . 'tests/JsonResponseTest.php');
require_once(AETHER_PATH . 'tests/ModuleFactoryTest.php');
require_once(AETHER_PATH . 'tests/SectionFactoryTest.php');
require_once(AETHER_PATH . 'tests/ServiceLocatorTest.php');
require_once(AETHER_PATH . 'tests/UrlParserTest.php');
require_once(AETHER_PATH . 'tests/templating/SmartyTest.php');
require_once(AETHER_PATH . 'tests/templating/TemplateTest.php');

/**
 * 
 * Run all PHPUnit test cases for aether
 * 
 * Created: 2009-02-17
 * @author Raymond Julin
 * @package aether.test
 */

class Framework_AllTests {
    public static function suite() {
        $suite = new PHPUnit_Framework_TestSuite('Aether Framework');
        $suite->addTestSuite('AetherCLITest');
        $suite->addTestSuite('AetherConfigTest');
        $suite->addTestSuite('AetherJsonCommentFilteredResponseTest');
        $suite->addTestSuite('AetherJsonResponseTest');
        $suite->addTestSuite('AetherModuleFactoryTest');
        $suite->addTestSuite('AetherSectionFactoryTest');
        $suite->addTestSuite('AetherServiceLocatorTest');
        $suite->addTestSuite('AetherUrlParserTest');
        $suite->addTestSuite('SmartyIntegratesWithAetherTest');
        $suite->addTestSuite('AetherTemplateTest');
        return $suite;
    }
}
?>
