<?php // 
require_once('PHPUnit/Framework.php');
require_once('/home/lib/libDefines.lib.php');
require_once(AETHER_PATH . 'lib/AetherTemplate.php');
/**
 * 
 * Test basic templating facade
 * 
 * Created: 2009-04-23
 * @author Raymond Julin
 * @package
 */
class AetherTemplateTest extends PHPUnit_Framework_TestCase {
    public function testGetTemplateObject() {
        $tpl = AetherTemplate::get('smarty');
        $this->assertTrue($tpl instanceof AetherTemplateSmarty);
    }
}

?>
