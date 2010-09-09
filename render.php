<?php
defined('APP_PATH') or define('APP_PATH', null);

class fw_ViewRenderer
{
    protected $variables;

    protected $filepath;
    protected $filename;
    protected $basedir;

    /**
     * @var string  Parent view script filename if any
     *               relative to a basedir object property
     *                      
     * @see fw_ViewRenderer::basedir
     */
    protected $parent   = null;

    /** @var array        Rendered blocks if any */
    protected $blocks   = array();

    /** @var string|null  Current block name if any */
    protected $in_block = null;

    /** @var array         HTTP headers set from view script */
    protected $headers  = array();    

    /**
     * Create new view script renderer
     *
     * We pass just an array of variables to the renderer which deals with loading
     * of view scripts and evaluating blocks. All other preprocessing should be done
     * before in fw_View where data, controls and theme are inputs.
     * 
     * If APP_PATH is defined and there is APP_PATH/helpers.php it will be loaded.
     * 
     * @param string $basedir   View script basedir
     * @param string $filename  View script file path relative to basedir
     * @param array $variables  Variables for the view script context  
     * 
     * @throws Exception        View script not found
     */
    public function __construct($basedir, $filename, $variables)
    {
        $this->filepath = $basedir.'/'.$filename;

        if(!is_file($this->filepath))
            throw new Exception('View script '.$this->filepath.' not found.');

        $this->filename  = $filename;
        $this->basedir   = $basedir;
        $this->variables = $variables;

        // load application view helpers: convention over configuration
        if(APP_PATH && is_file(APP_PATH.'/helpers.php')) {
            require_once(APP_PATH.'/helpers.php');
        }
    }

    /**
     * Set HTTP header for the return object from the view script
     * 
     * This it the wrapper for PHP header() function and can be used if
     * view script renders something other then HTML (attachment, filename, etc.)
     * 
     * @link http://php.net/manual/en/function.header.php
     * 
     * @param $string               The header string.
     * @param $replace              Replace a previous similar header
     * @param $http_response_code   Forces the HTTP response code
     */
    protected function set_header($string, $replace = true, $http_response_code = null)
    {
        $this->headers[] = array($string, $replace, $http_response_code);
    }

    /**
     * Set view script as extended from the parent view script.
     * 
     * First the current script and all blocks from it would be rendered,
     * after which the parent script would be loaded and rendered using
     * block names as context variables.
     * 
     * @param string $filename      Parent view script relative to the current one
     */
    protected function inherit($filename)
    {
        $this->parent = $filename;
    }

    /**
     * Begin a new named block.
     * 
     * After the block is closed all output in between will get passed to the
     * parent script if any as a view script context variable. Blocks have
     * access to current view script context.
     * 
     * @param string $block         Block name
     */
    protected function begin_block($block)
    {
        $this->in_block = $block;
        ob_start();
    }

    /**
     * End current block.
     * 
     * @throws Exception             Not in block
     */
    protected function end_block()
    {
        if(!$this->in_block) {
            ob_end_clean();
            throw new Exception('End block called while not in block context.');
        }

        $this->blocks[$this->in_block] = ob_get_contents();
        ob_end_clean();
    }

    /**
     * Run new renderer context on a view script and return output as block.
     * 
     * Current view script variables context will be accessible using $parent
     * variable in the new context.
     * 
     * @param string $block          Block name
     * @param string $filename       Block view script relative to the current one
     * @param array $variables       Variables context
     */
    protected function set_block($block, $filename, $variables)
    {
        $variables['parent'] = &$this->variables;

        $renderer = new fw_ViewRenderer($this->basedir, $filename, $variables);
        $output = $renderer->render();
        
        array_merge($this->headers, $output->get_headers());
        $this->blocks[$block] = $output->get_data();        
    }

    /**
     * Render the view script.
     * 
     * @return fw_ViewOutput    Output object
     */
    public function render()
    {
        // Extract variables into the current context
        extract($this->variables, EXTR_SKIP|EXTR_REFS);

        ob_start();
        require($this->filepath);
        $out = ob_get_contents();
        ob_end_clean();
        
        if(!is_null($this->parent)) {
            // Recursively render the parent script 
            $variables = array_merge($this->variables, $this->blocks);
            $renderer = new fw_ViewRenderer($this->basedir, $this->parent, $variables);
            $output = $renderer->render();
            
            array_merge($this->headers, $output->get_headers());
            $out = $output->get_data();  
        }
        
        return new fw_ViewOutput($out, $this->headers);
    }
}

class fw_ViewOutput
{
    protected $headers;
    protected $data;
    
    public function __construct($data, $headers = null)
    {
        $this->data    = $data;   
        $this->headers = $headers;
    }
    
    public function get_headers()
    {
        return $this->headers;
    }
    
    public function get_data()
    {
        return $this->data;
    }
}

?>
