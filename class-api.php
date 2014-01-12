<?php

require_once ( BB_WP_API_PATH .  '/class-api-handler.php');

/**
 * BB_WP_API class.
 *
 * setter to configure the api
 * loads js backbone extension file
 * sets up a listener with error checking
 * some static helper methods
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
	
		/* adds js backbone extension and passes some vars to */
		add_action ( 'wp_enqueue_scripts', array($this,'register_javascript') );
	}
	
	/**
	 * register_javascript function.
	 *
	 * also passes important vars to js
	 * 
	 * @category action hook callback
	 * @access public
	 * @return void
	 */
	public function register_javascript() {
			
		/* backbone js extension */
		wp_enqueue_script( 'bb-wp-api', plugins_url( '/bb-wp-api.js' , BB_WP_API_FILE ) ,  array( 'jquery', 'backbone') );
		
		/* set some public vars for being accessable in bb */
		$java_vars =  array( 
						'ajaxUrl' 			=> admin_url( 'admin-ajax.php'),
						'action'			=> $this->actionname
					  );	
		wp_localize_script( 'backbone', 'bbWpApiExternalVars_' . $this->id , $java_vars);  		
	}
	
	/**
	 * listen function.
	 *
	 * waiting for ajax requests
	 * make checks, 
	 * load the handler for the model_id sent in the request
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
		$model_Id 	= $_REQUEST['model_Id']; // id to select the corresponding handler class
		$model 		= $_REQUEST['model']; // the data
		
		/* clean up */
		unset($_REQUEST['model_Id']); //dont need anymore
		
		/* validation */
		if( ! $this->_is_valid_method($method))
			$this->set_error( 1, 'No valid method in response header' );
			
		/* gets an handler or throws an error		 */
		$handler = $this->_maybe_get_valid_handler($model_Id);
							
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
			wp_send_json( array('errors' => $this->get_errors()) );
		}
	}	

	/**
	 * set_id function.
	 * 
	 * @access public
	 * @param string $id
	 * @return bool | false
	 */
	public function set_id( $id ) {
		if( ! is_string($id))
			return false;
		$this->id = esc_attr($id);
			return $this->id;	
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
	 * @param mixed $model_Id
	 * @return void
	 */
	private function _maybe_get_valid_handler( $model_Id ) {
		
		/* convert the name */
		$handlerclassname = self::model_Id_to_handler_classname($model_Id); 

		/* try if we got a real name for a class */
		if( ! class_exists($handlerclassname) ) {
			$this->set_error( 2, 'the passed model_Id is not supported by the server' );
			return false;
		}

		/* try to make an instance ofthe class */
		$instance = new $handlerclassname;		
		
		/* check if the instance is really a handler */
		if( ! $instance instanceof BB_WP_API_Handler  ) {
			$this->set_error( 2, 'The passed model_Id doesnt have a corresponding handler class' );
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
	 * model_Id_to_handler_classname function.
	 * 
	 * convert the js model_Id to the hanler classname
	 *
	 * @access public
	 * @static
	 * @param mixed $handler_id
	 * @return string
	 */
	public static function model_Id_to_handler_classname( $model_Id ) {
		
		return  self::_id_to_handler_classname( $model_Id, 'BB_WP_API_Handler_' );		
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