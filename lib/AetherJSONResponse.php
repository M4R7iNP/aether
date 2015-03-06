<?php // vim:set ts=4 sw=4 et:
/**
 * 
 * JSON response
 * 
 * Created: 2008-10-23
 * @author Raymond Julin
 * @package aether.lib
 */

class AetherJSONResponse extends AetherResponse {
    
    /**
     * Hold text string for output
     * @var string
     */
    private $struct;
    
    /**
     * Hold cached output
     * @var string
     */
    private $out = '';
    
    /**
     * Constructor
     *
     * @access public
     * @return AetherJSONResponse
     * @param array $structure
     */
    public function __construct($structure) {
        $this->struct = $structure;
    }
    
    /**
     * Draw text response. Echoes out the response
     *
     * @access public
     * @return void
     * @param AetherServiceLocator $sl
     */
    public function draw($sl) {
        if ($this->struct !== null) {
            header("Content-Type: application/json; charset=UTF-8");
        }
        echo json_encode($this->get());
    }
    
    /**
     * Return instead of echo
     *
     * @access public
     * @return string
     */
    public function get() {
        return $this->struct;
    }
}
