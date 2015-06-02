<?php

require_once ( BB_WP_API_PATH .  '/class-api-handler.php');

/**
 * BB_WP_API class.
 *
 * setter to configure the api
 * loads js backbone extension file
 * sets up a listener with error checking
 * some static helper methods
 * public methods fo defining the action port, the api id, registering bootstrapdata, for starting the listeners
 * 
 *
 * @package backbone wordpress api
 * @author sef
 * @version 0
 */
class BB_WP_API {

	/**
	  * methods
	  * 
	  * supported request methods sent by backbone
	  * 
	  * @category whitelist
	  * @var string
	  * @access private
	  * @static
	  */
	private static $methods = array('read', 'create', 'delete', 'update');

	/**
	  * id
	  *
	  * id of the instance of this api
	  * bind the instance to models on backbone by defining "api_Id" for each model
	  * 
	  * @var string
	  * @access private
	  */
	private $id = 'default' ;
	
	/**
	 * actionname
	 * 
	 * change with setter 
	 * can be useful to change when you want your own port so to speak
	 * 
	 * @var string
	 * @access private
	 */
	private $actionname = 'bb_wp_api_port';
	
	/**
	 * bootstrap_data
	 * 
	 * static vars passed to backbone
	 * 
	 * @var array
	 * @access private
	 */
	private $bootstrap_data = array();
  
  
  /**
   * backbone_js
   * 
   * belT and backbone and a global JS Object is loaded when set to true
   *
   * (default value: true)
   * 
   * @var bool
   * @access private
   */
  private $backbone_js = true;
  
  
	/**
	 * errors
	 * 
	 * empty error object to store all errors in this class
	 *
	 * @var WP_Error
	 * @access private
	 */
	private $errors;

	
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {
		// set an empty error obj
		$this->errors = new WP_Error;
	}
	
	/* do not allow */
	private function __clone(){}
	
	/**
	 * start function.
	 * 
	 * runs the api
	 * dont run the api, before all settings were set
	 *
	 * @category init
	 * @access public
	 * @return void
	 */
	public function start() {
		
		if( ! $this->actionname ) 
			$this->set_error( 100, 'add an ajax port first for listening' );
	
		/* start listening on ajax port */
		add_action ( 'wp_ajax_' . $this->actionname, array($this,'listen') );
		add_action ( 'wp_ajax_nopriv_' . $this->actionname, array($this,'listen') );
    
    if( $this->backbone_js ) {
  		/* adds js backbone extension and passes some vars to */
  		add_action ( 'wp_enqueue_scripts', array($this,'register_backbone_javascript') );		      
    }
    
    add_action( 'wp_head', array( $this, 'print_vars' ));
	}

	/**
	 * set_name function.
	 *
	 * give every api an id string
	 * 
	 * @access public
	 * @param string $id
	 * @return string | false
	 */
	public function set_name( $name ) {
		if( ! is_string($name))
			return false;
		$this->name = esc_attr($name);
			return $this->name;	
	}
	
	/**
	 * set_actionname function.
	 * 
	 * set an ajax action name
	 *
	 * @access public
	 * @param mixed $name
	 * @return bool | void
	 */
	public function set_actionname( $name ) {
		if( ! is_string($name))
			return false;
		$this->actionname = esc_attr($name);
			return $this->actionname;	
	}

	/**
	 * unset_backbone function.
	 * 
	 * disable Backbone
	 *
	 * @access public
	 * @return void
	 */
	public function unset_backbone() {
		$this->backbone_js = false;
	}
	
	
	/**
	 * set_bootstrap_data function.
	 * 
	 * performs an api call on page load and passes it to backbone
	 * therefore no initial ajax call is needed to load models at backbone after pageload
	 *
	 * @access public
	 * @param string name // a name to identify the data
	 * @param string $handler // backbone modelname identifier
	 * @param int $object_id // the wp id of a specific post, user, comment, if NULL all matching objects are returned
	 * @return void
	 */
	public function set_bootstrap_data($name, $handler, $object_id = NULL ) {
	
		$handler = $this->_maybe_get_valid_handler($handler);
		if($this->get_errors())
			return false;
		/* $handler->set_request(array('queryvars' => array('posts_per_page' => 1)));		 */
		$handler->read($object_id);					
		$this->bootstrap_data[$name] = $handler->get_response( array('json' => false) );
	}
		
	/**
	 * register_backbone_javascript function.
	 *
	 * also passes important vars to js
	 * 
	 * @category action hook callback
	 * @access public
	 * @return void
	 */
	public function register_backbone_javascript() {
			
		/* backbone js extension */
		wp_enqueue_script( 'belT', plugins_url( '/belt.js' , BB_WP_API_FILE ) ,  array( 'jquery', 'backbone') );		  	
		wp_localize_script( 'backbone', 'belTExternalVars_' . $this->name , $this->get_js_vars());  		
	}
	
	public function print_vars() { ?>
  	
		<script type='text/javascript'>
		/* <![CDATA[ */
  
    var BB_WP_API = BB_WP_API || {};
    BB_WP_API.instances = BB_WP_API.instances || {};
    
    BB_WP_API.instances['<?php echo $this->name;?>'] = <?php echo json_encode( $this->get_js_vars());?>
    
		/* ]]> */
		</script>

  <?php
	}
	
	/**
	 * listen function.
	 *
	 * waiting for ajax requests
	 * make checks, 
	 * load the handler class for the handler identifier sent in the request (factory pattern)
	 * let handler do the work and parse the response
	 * return a reponse
	 * 
	 * @category action hook callback
	 * @access public
	 * @return void
	 */
	public function listen() {			
		/* get values from request */
		$method 	= $_REQUEST['method']; // get, creat, update or delete
		$handler 	= $_REQUEST['handler']; // id to select the corresponding handler class
		$model 		= $_REQUEST['model']; // the data
		
		/* clean up */
		unset($_REQUEST['handler']); //dont need anymore
		
		/* validation */
		if( ! $this->_is_valid_method($method))
			$this->set_error( 1, 'No valid method in response header' );
			
		/* gets an handler or throws an error		 */
		$handler = $this->_maybe_get_valid_handler($handler);
			
		// the model data can also be sent over payload	
		if ( isset( $_REQUEST['payload'] )) {
      $request_body = file_get_contents('php://input');
      $payload = json_decode($request_body, true); // true for an associative array, not an object
      $_REQUEST['model'] = $payload['model'];			  		
      if( isset( $payload['queryvars'] ) && ! empty( $payload['queryvars'] )) {
        $_REQUEST['queryvars'] = $payload['queryvars'];			  		      
      }
		}	
					
		/* all tests passed, now call the handler */
		if( ! $this->get_errors() ) {
			
			/* prepare */
			$handler->set_request($_REQUEST);
			
			/* do some action */
			switch ($method) {
				case 'read':
					$result = $handler->read();					
				break;				
				case 'create':	
					$result = $handler->create();		
				break;
				case 'update':
					$result = $handler->update();		
				break;
				case 'delete':
					$result = $handler->delete();		
				break;
			}
			
			// successful reponse being sent
			$handler->send_response();	
					
		} else {
		
			/* send error object from the api */
			wp_send_json_error( $this->get_errors()) ;
		}
	}	
	
	/**
	 * set_error function.
	 * 
	 * @access protected
	 * @param string $code
	 * @param string $message
	 * @return void
	 */
	protected function set_error( $code, $message) {
		$this->errors->add($code, $message);
	}
	
	/**
	 * get_errors function.
	 * 
	 * @access public
	 * @return array
	 */
	public function get_errors() {
		return $this->errors->get_error_messages();
	}
	
	/**
	 * _maybe_get_valid_handler function.
	 * 
	 * @access private
	 * @param mixed $handler
	 * @return void
	 */
	private function _maybe_get_valid_handler( $handler ) {
		
		/* convert the name */
		$handlerclassname = self::handler_to_handler_classname($handler); 

		/* try if we got a real name for a class */
		if( ! class_exists($handlerclassname) ) {
			$this->set_error( 2, 'the passed handler is not supported by the server' );
			return false;
		}

		/* try to make an instance ofthe class */
		$instance = new $handlerclassname;		
		
		/* check if the instance is really a handler */
		if( ! $instance instanceof BB_WP_API_Handler  ) {
			$this->set_error( 2, 'The passed handler doesnt have a corresponding handler class' );
			return false;		
		}
		
		/* indeed, this is our valid handler */
		return $instance; 			
	}
	
	/**
	 * _is_valid_method function.
	 * 
	 * @access private
	 * @param string $method
	 * @return void
	 */
	private function _is_valid_method($method) {
		return ( in_array($method, self::$methods) ) ? $method : false;	
	}
	
	/**
	 * handler_to_handler_classname function.
	 * 
	 * convert the js handler to the hanler classname
	 *
	 * @access public
	 * @static
	 * @param mixed $handler_id
	 * @return string
	 */
	public static function handler_to_handler_classname( $handler ) {
		
		return  self::_id_to_handler_classname( $handler, 'BB_WP_API_Handler_' );		
	}	

	/**
	 * _id_to_handler_classname function.
	 * 
	 * transforms string from "review-model" to "Class_Review_Model"
	 *
	 * @access public
	 * @static
	 * @param string $id
	 * @param string $classprefix (default: 'Class_')
	 * @return string
	 */
	public static function _id_to_handler_classname( $id, $classprefix = 'Class_' ) {
					
		/* all capitalize and replace - with _ */
		$parse = str_replace("-", "_", $id);
		$parse = explode('_', $parse);
		$parse = array_map("ucfirst", $parse);
		
		return $classprefix . implode('_', $parse);		
	}	
	
	/**
	 * _handler_classname_to_id function.
	 * 
	 * draft
	 *
	 * @TODO
	 * @access public
	 * @static
	 * @param mixed $handlerclassname
	 * @param string $classprefix (default: 'Class_')
	 * @return void
	 */
	public static function _handler_classname_to_id( $handlerclassname, $classprefix = 'Class_') {
		
		$parse = str_replace($classprefix, '', $handlerclassname);
		$parse = explode('_', $parse);
		$parse = array_map("strtolower", $parse);
		return  implode('-', $parse);			
	}

  /**
   * get_js_vars function.
   * 
   * an array with all JS vars
   *
   * @access private
   * @return array
   */
  private function get_js_vars() {
    
		/* set some public vars for being accessable in bb */
	  $java_vars =  array( 
					'ajaxUrl' 			=> admin_url( 'admin-ajax.php'),
					'action'			=> $this->actionname
    );
				  
		/* include bootstrapdata if any set */
		if($this->bootstrap_data) {
			foreach ($this->bootstrap_data as $name => $record) {
				$java_vars[$name] = $record;			
				
			}
		}
		return $java_vars;
  }

	/**
	 * get_current_url function.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function get_current_url() {
		 $page_url = 'http';
		 if ($_SERVER["HTTPS"] == "on") {$page_url .= "s";}
		 $page_url .= "://";
		 if ($_SERVER["SERVER_PORT"] != "80") {
		  $page_url .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
		 } else {
		  $page_url .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		 }

		 // this is only for debugging to make codekit work
		$page_url = preg_replace('/([^?]+)\?[^?]*/', '$1', $page_url); // remove all after the ?

		return esc_url($page_url);
	}
}