<?php


require_once ( BB_WP_API_PATH .  '/class-api-registration-unit.php');
require_once ( BB_WP_API_PATH .  '/class-api-data-package-handler.php');
require_once ( BB_WP_API_PATH .  '/api-handler/class-api-handler-abstract-post.php');
require_once ( BB_WP_API_PATH .  '/api-handler/class-api-handler-review.php');
require_once ( BB_WP_API_PATH .  '/api-handler/class-api-handler-attachment.php');
require_once ( BB_WP_API_PATH .  '/api-handler/class-api-handler-comment.php');
require_once ( BB_WP_API_PATH .  '/api-handler/class-api-handler-user.php');
require_once ( BB_WP_API_PATH .  '/api-handler/class-api-handler-loginuser.php');




/**
 * Abstract BB_WP_API_Handler class.
 * 
 *
 * CORE
 *
 * @use $this->set_request to bind a request from backbone
 * call $this->read, create, update or delete to process the request
 *
 * the read method 
 * @use $this->read
 * calls $this->_query_single or $this->_query_all
 * 
 * the create, update, delete method 
 * @use $this->create, update, delete
 * calls $this->parse_model_request to parse the incoming model from backbone
 * interacts with the database
 * 
 * after the action (read, create, update, delate) $this->parse_model_response ...
 * ...is called to parse the response, ready to send to backbone
 *
 * @use $this->send_response to terminate the handler and send the response to wp
 *
 * REGISTER
 * 
 * calls Class BB_WP_API_Registration_Unit
 * supply register_data() in the child class to register all needed data
 * @use this class to extend
 *
 * DATAPACKAGE HANDLER
 * 
 * calls Class BB_WP_API_Handler_Data_Package
 * joins registered data sets to the handler
 * processing is done by to helper functions under the hood
 *
 * @abstract
 */
abstract class BB_WP_API_Handler {
	
	/**
	 * debugger
	 * 
	 * 
	 * @var bool
	 * @access private
	 */
	private $debugger = false;
		
	/**
	 * modelclasses
	 * 
	 * whitelist for supported modelclasses
	 * post represents wp posttype
	 * comment represents a wp comment
	 * user represents a WP_User
	 *
	 * those cant be extended by a child class
	 * 
	 * @var array
	 * @access private
	 */
	private $modelclasses = array('post', 'comment', 'user', 'attachment', 'idone');	
	
	/**
	 * core_data_packages
	 * 
	 * the handling of those 2 datapackages in hardcoded
	 * distinguish modelclass and core data packages:
	 * the modelclass is the overall object, a modelclass contains various data packages, the core data packages are such
	 * 
	 * @var array
	 * @access protected
	 */
	protected $core_data_packages = array('post', 'comment', 'user');
	 
	/**
	 * request
	 * 
	 * The raw request, received from backbone over AJAX
	 * All wich was sent is in there
	 * Used only by the parser methods
	 *
	 * @var array
	 * @access protected
	 */
	private $request;
	
	/**
	 * request_method
	 * 
	 * read, create, put, delete
	 *
	 * @var string
	 * @access protected
	 */
	protected $request_method;
	
	/**
	 * request_query_vars
	 * 
	 * query vars from backbone collection, fetch
	 * 
	 * @var array
	 * @access protected
	 */
	protected $request_query_vars = array();
	
	/**
	 * properties
	 *
	 * all the registered properties from the extended class
	 * will be populated by registration unit 
	 * check the registration unit class for more info
	 * 
	 * @var std object
	 * @access protected
	 */
	protected $properties = array();
	
	/**
	 * id
	 * 
	 * when there is an id sent in the request it is stored here
	 * this id represents the id of the model stored in the database
	 * 
	 * @var int
	 * @access protected
	 */
	protected $id = 0;
	
	/**
	 * parent_id
	 * 
	 * some posts like attachments or comments require a parent post to be registered
	 * 
	 * @var int
	 * @access public
	 */
	protected $parent_id = 0;
	 
	/**
	 * parsed_model_request
	 * 
	 * incoming model from request is stored here after being parsed for further wp processing
	 * used for create, update, and delete methods
	 *
	 * @var array
	 * @access protected
	 */
	protected $parsed_model_request;
	 
	/**
	 * query
	 * 
	 * the database results for models are stored
	 * used for read requests 
	 *
	 * @var array
	 * @access protected
	 */
	protected $query = array();
 	 
	/**
	 * parsed_model_response
	 * 
	 * parsed model from query, ready for sending to backbone 
	 *
	 * @var array
	 * @access protected
	 */
	public $parsed_model_response = false;
	
	/**
	 * parsed_response
	 * 
	 * the complete response, ready to return to backbone
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $parsed_response;
	
	/**
	 * current_user
	 *
	 * the current wp user
	 * 
	 * @var WP_User
	 * @access protected
	 */
	protected $current_user;
	
	/**
	 * errors
	 * 
	 * keeps all errors for the respond
	 * 
	 * @var WP_Error
	 * @access private
	 */
	private $errors = NULL;		

	 
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @param array $request 
	 * @return void
	 */
	public function __construct($request = NULL) {
	 		  
	 	/* 
	 	 * register all data for the handler from the child class
	 	 *		
	 	 * this class handles all settings to be registered from the Extended Handler Class
		 * it will be insanized on construct and destroyed immediatly after registration
	 	 */	 
	 	
	 	/* make an object that handles registration	  */
	 	$register = new BB_WP_API_Registration_Unit( 
	 		array( 	'core_data_packages' => $this->core_data_packages,
	 				'modelclasses' => $this->modelclasses
	 		)
	 	);
	 	
	 	/* call the abstract method with the registration data, and pass the registration object to it */
	 	/* this method must exist in the child class  */
	 	$this->register_data($register); 
		
		/* get the registered data	 */
		$this->properties = $register->get_properties();
		$register = NULL; // destroy registration unit 
		
		/* set the current wp user */
	 	$this->current_user = wp_get_current_user();
	 	
	 	/* set an error object */
	 	$this->errors = new WP_Error();

	 	/* 
	 	 * error checking	
	 	 * send the response with an rerror message right away if something happend already so far
	 	 */
	 	 
	 	/* a valid  modelclass is rerquired */
	 	if( ! $this->properties->modelclass )
			$this->set_error( 55, 'no valid modelclass registered' );			
	 		
	 	/* access method */
	 	if ( ( $this->properties->access == "loggedin" ) && ( ! is_user_logged_in() ) ) {
			$this->set_error( 56, 'user must be logged in for this request' );				 	
	 	}
	 	
		/* request can be set as an constructor argument or via the setter */
	 	if($request)
	 		$this->set_request($request);
			 
	}


	/* ===============
	   GETTER & SETTER
	   =============== */
 
	/**
	 * set_request function.
	 * 
	 * passes the request to the handler class
	 *
	 * @access public
	 * @final
	 * @param array $request (default: array())
	 * @return void
	 */
	public final function set_request($request = array()) {
	 	
	 	/* set vars from the server request */
		$this->request = $request;
		
		/* $this method is also when the corresponding action is called */
		if( isset($request['method']))
			$this->request_method = $request['method'];
			 
		$this->request_url = esc_url($request['requesturl']);
		if( isset($request['queryvars']))
			$this->request_query_vars = (array) $request['queryvars']; // array is expected
		
		$id = $this->_find_in_request($this->properties->id_attribute);
		$this->id = $id;		
		$this->parent_id = $this->_find_in_request($this->properties->parent_id_attribute);	
	}	
	
	/**
	 * set_error function.
	 * 
	 * throw an error
	 *
	 * @access protected
	 * @param int | string $code
	 * @param string $message
	 * @return void
	 */
	protected function set_error( $code, $message) {
		$this->errors->add($code, $message);
	}
	
	/**
	 * get_errors function.
	 * 
	 * get all errors occured in this handler
	 *
	 * @access public
	 * @return array
	 */
	public function get_errors() {
		return $this->errors->get_error_messages();
	}
	
	
	/**
	 * merge_errors function.
	 * 
	 * merges a new WP_Error with the classes WP_Error
	 * merge is done very loosly, but it works for the purpose of the class
	 *
	 * @access public
	 * @param mixed $wp_error
	 * @return void
	 */
	public function merge_errors($wp_error) {
		$new_errors = $wp_error->get_error_messages();
		foreach ($new_errors as $code => $message) {
			$this->errors->add($code, $message);		
		}			    
	}
	/* ===============
	   MAIN CONTROLLER
	   =============== */
	   
	   
	/**
	 * action function.
	 * 
	 * a wrapper method for create, update, read, delete
	 *
	 * @access public
	 * @return void
	 */
	public function action() {
		if( ! $this->request_method )
			return false;
		switch ($this->request_method) {
			case 'read':
				$this->read();					
			break;				
			case 'create':	
				$this->create();					
			break;
			case 'update':
				$this->update();					
			break;
			case 'delete':
				$this->delete();					
			break;
		}
	}
	
	/**
	 * read function.
	 * 
	 * prepares the wp response for a backbone read request
	 *
	 * must call parse_model_response to parse the result
	 *
	 * @access public
	 * @return void
	 */
	public function read( $id = null ){
		if($this->get_errors())
			return;
		
		/* set method */
		$this->request_method = 'read';
		
		/* read the data from backbone */
		$this->parse_model_request();
		
		/* set id */
		if($id)
			$this->id = $id;
			
		/* call the database query */
		if($this->id) 
			$query = $this->_query_single();
		else
			$query = $this->_query_all();
					
		/* format the retrieved models */
		$this->parse_model_response($query);	
	}
	 
	/**
	 * create function.
	 * 
	 * processes the wp response for a backbone create request
	 * saves the model to database after error checking 
	 * id of the new object will be returned
	 *
	 * must call parse_model_response to parse the result
	 *
	 * @access public
	 * @return void
	 */
	public function create() {
		if($this->get_errors())
			return;
			
		/* set method */
		$this->request_method = 'create';
				
		/* check for a model id */
		if( $this->id) {
			$this->set_error( 4, 'A model id in request, cant create a new item on server' );
			return;			
		}
		
		/* read the data from backbone */
		$this->parse_model_request();

		/* get the parsed post data from request */
		$item_data = $this->parsed_model_request;

		/* privileg checking @TODO outsource to filter */
		if( ! current_user_can('edit_posts')) {  // TODO improve CPT
			$this->set_error( 9, 'no user privileges to save the item on server' );
			return;
		}	
				 	
		/* insert database */
		switch( $this->properties->modelclass ) {
			
			/* posts */
			case('post'):
				$post = $item_data['post'];							
				$result = wp_insert_post( $post );
				
				/* was there an error saving the post */
				if( ! $result) {
					$this->set_error( 6, 'saving the item failed on the server' );
					return;
				}
				
				/* got the id of the created post */
				$new_id = $result;	
			break;

			case('attachment'):
				//@TODO
				$attachment = $item_data['post'];	
				
				/* a post parent for a attachment must be set */			
				if( ! $this->parent_id ) {
					$this->set_error( 7, 'the attachment has no parent id' );
					return;
				}				
				$new_id = media_handle_upload('async-upload', $this->parent_id, $attachment);


/* 				$new_id = wp_insert_attachment( $attachment, $attachment['guid'], $attachment['post_parent'] ); */
				
				// create all the metadata for the attachment, like wp image sizes
				//do_action( 'bb_wp_api_after_create_attachment',$new_id ,$attachment['guid'] );
		
			break;

			
			/* comment */
			case('comment'):
				$comment = $item_data['comment'];

				/* a post parent for a comment must be set */			
				if( ! $this->parent_id ) {
					$this->set_error( 7, 'the comment has no parent id' );
					return;
				}
				
				/* in some odd cases it maybe that the parent id is not yet in the comment array */
				$comment['comment_post_ID'] = $this->parent_id;
				
				$result = wp_insert_comment($comment);
				
				/* was there an error saving the comment */							
				if( ! $result) {
					$this->set_error( 8, 'saving the comment failed on the server' );
					return;
				}
				
				/* got the id of the created comment */
				$new_id = $result;
			break;
			case('user'):
			//@TODO
			break;
			case('idone'):
				$new_id = $this->id;			
			break;
		}	
		
		/* save custom data, this only method does all the magic, details must be specified in the custom package handlers */
		$this->_action_custom_package_data( $new_id, $item_data);	
		
		/* set a clean response */
		$this->parse_model_response($new_id);	
	}
	
	/**
	 * update function.
	 * 
	 * processes the wp response for a backbone update request
	 * updates an existing wp object
	 *
	 * @access public
	 * @return void
	 */
	public function update(){
		if($this->get_errors())
			return;
			
		/* set method */
		$this->request_method = 'update';
				
		/* check for a model id */
		if( ! $this->id) {
			$this->set_error( 10, 'No model id in request, cant update' );
			return;			
		}
		
		/* read the data received from backbone */
		$this->parse_model_request();
		
		/* get the parsed post data from request */
		$item_data = $this->parsed_model_request;
		  			 	
		/* insert database */
		switch( $this->properties->modelclass ) {
		
			/* posts */
			case('post'):
			case('attachment'):
				$post = $item_data['post'];
				
				/* privileg check */
				if( ! current_user_can('edit_post', $this->id)) {  
					$this->set_error( 11, 'no user privileges to update the item on server' );
					return;
				}	
				
				$result = wp_update_post( $post );
				
				/* maybe an error while updating */
				if( ! $result) {
					$this->set_error( 12, 'updating the item failed on the server' );
					return;
				}
				
				/* geting the id means update was a success */
				$updated_id = $result;
				
				/* save custom data, this only method does all the magic, 
				details must be specified in the custom package handlers */
				$this->_action_custom_package_data( $updated_id, $item_data);	
				
				/* set a clean response */
				$this->parse_model_response($updated_id);	
			break;
			
			/* comment */
			case('comment'):
				$comment = $item_data['comment'];
				
				/* comments cant be updated */
				$this->set_error( 13, 'comments cant be updated!' );
					return; 
			break;
			case('user'):
			//@TODO
			break;
			case('idone'):
				$new_id = $this->id;
				$this->_action_custom_package_data( $new_id, $item_data);					
				$this->parse_model_response($new_id);			
			break;
		}	
	}
	
	/**
	 * delete function.
	 * 
	 * processes the wp response for a backbone delete request
	 * deletes an existing wp object
	 *
	 * @access public
	 * @return void
	 */
	public function delete(){
		if($this->get_errors())
			return;
			
		/* set method */
		$this->request_method = 'delete';
				
		/* check for id */
		if( ! $this->id) {
			$this->set_error( 14, 'No model id in request, cant delete' );
			return;			
		}
		
		/* parse the data from backbone */
		$this->parse_model_request();
		
		/* access the parsed post data from request */
		$item_data = $this->parsed_model_request;		
		  			 	
		/* delete from database */
		switch( $this->properties->modelclass ) {
		
			/* posts */
			case('post'):
			case('attachment'):
				$post = $item_data['post'];
				
				/* privileg check */
				if( ! current_user_can('delete_post', $this->id)) {  
					$this->set_error( 15, 'no user privileges to delete the item on server' );
					return;
				}	
				$result = wp_delete_post($this->id);
				
				/* error checking while delete */
				if( ! $result) {
					$this->set_error( 16, 'deleting the item failed on the server' );
					return;
				}
				
				/* success, the id of the deleted post was returned */
				$deleted_id = $result->ID;
				
				/* setup a clean response */
				$this->parse_model_response($deleted_id);	
			break;
			
			/* comment */
			case('comment'):
				$comment = $item_data['comment'];
				/* @TODO better permission handling */
					$comment_in_db = get_comment( $this->id );
					$comment_author = $comment_in_db->user_id;
				if ( $comment_author != $this->current_user->ID ) {
					$this->set_error( 17, 'This comment can only be deleted by the author ' . $comment_author );
					return;	
				}
				$result = wp_delete_comment($this->id);
				
				/* true is returned on success delete */
				if( ! $result) {
					$this->set_error( 17, 'deleting the comment failed on the server' );
					return;
				}
				
				/* setup a clean response */	
				$this->parse_model_response($result);	
			break;
			case('user'):
			//@TODO
			break;
			case('idone'):
				$new_id = $this->id;
				$this->_action_custom_package_data( $new_id, $item_data);					
				$this->parse_model_response($new_id);			
			break;
		}	
	}

	/**
	 * get_response function.
	 * 
	 * get the full response that will be sent to the server
	 * 
	 * @access public
	 * @return void
	 */
	public function get_response($options = array('json' => false) ) {

		/* collect the response in an array ... */
		$return = array();
		
		/* access the response prepared model response by the main controllers ($this->read, $this->create, ...) */
		$return = $this->parsed_model_response;
					
		/* add errors to the response if we have any */
		if($this->get_errors())
			$return = $this->get_errors();	
		
		/* when debug is on send the whole instance */
		if($this->debugger) {
			echo (print_r($this));
			die(); 	
		}
		
		/* allow a filter to change / add the response */
		$return = $this->filter_response($return);
		
		/* get through the output options */
		if($options['json']) 
			return json_encode($return);
		else 
			return $return;
	}
	
	/**
	 * send_response function.
	 * 
	 * finally sends back the respone to wp and terminates the class
	 * 
	 * @access public
	 * @return void
	 */	
	public function send_response() {
	
		$return = $this->get_response();
		
		/* send it and terminate this instance */
		if($this->get_errors()) {
			wp_send_json_error($return);						
		} else {
			wp_send_json_success($return);			
		}
	} 
	
	/* ===============
	   FILTER
	   =============== */
	   
	   
	   /**
	    * filter_id function.
	    * 
	    * allows to modify the id from the get request method
	    *
	    * @access protected
	    * @param mixed $id
	    * @return void
	    */
	   protected function filter_id($id) {
		   return $id;
	   }

	/**
	 * filter_pre_parse_model_request function.
	 * 
	 * filter the model request before being parsed
	 * can be used to set default values that may be overwritten during parsing
	 *
	 * @access protected
	 * @param array $parsed
	 * @param string $modelmethod
	 * @return array
	 */
	protected function filter_pre_parse_model_request( $parsed, $modelmethod ) {    	
    	return $parsed;
	}
		
	/**
	 * filter_post_parse_model_request function.
	 * 
	 * filter the model request after being parsed
	 * can be used to override values
 	 *
	 * @access protected
	 * @param array $parsed
	 * @param string $modelmethod
	 * @return array
	 */
	protected function filter_post_parse_model_request( $parsed, $modelmethod ) {    	
    	return $parsed;
	}
	
	/**
	 * filter_query_args function.
	 * 
	 * use this filter to override queryvars sent from backbone
	 *
	 * @access protected
	 * @param array $queryargs
	 * @return array
	 */
	protected function filter_query_args($queryargs) {
		return $queryargs;
	}

    /**
     * filter_response function.
     * 
     * add something to the response right before it is sent back to backbone
     *
     * @access protected
     * @param array $return
     * @return array
     */
    protected function filter_response($return) {
	    return $return;
    }
    
 	/* ===============
	   PARSER
	   =============== */   
   
	/**
	 * parse_model_request function.
	 *
	 * prepare the incomin backbone model request for further processing
	 * for create, update, delete requests
	 * sets 
	 *
	 * @access protected
	 * @final
	 * @return void
	 */
	protected final function parse_model_request() {
	    
	    /* get the model from the request */
	    $modeldata = (is_array($this->request['model'])) ? $this->request['model'] : array();
	    /*
	     *	looks something like this now:
		 *	$modeldata = array(
		 *   	'title'				=> 'my title',
		 *   	'content'			=> 'some content',
		 *      'any backbone key'	=> 'any backbone value'
		 *  );
		 */
	    	    
	   	/* the formatting of the parsed output  */
    	$parsed = array();
    	
    	/* get all data packages, also the core packages and parsse them */
	   	$data_packages = array_merge($this->core_data_packages, $this->properties->custom_data_packages ); 
	   	foreach ($data_packages as $data_package) {
		   	$parsed[$data_package] = array();
	   	}   	
    	/*
	     *	looks something like this now:
		 *	$parsed = array(
		 *   	'post'							=> array(),
		 *   	'postmeta'						=> array(),
		 *   	'comment'						=> array(),
		 *		'any registered data package' 	=> array()
		 *  );
		 */
 
    	/* get default values if some are set in the child handler*/
     	$parsed = $this->filter_pre_parse_model_request($parsed, $this->request_method);
   	
     	/* start parsing */
		foreach ($modeldata as $id_backbone => $value) {
			
			/* check if the data package field is registered */
			/* only data_package_fields registered in backbone pass the gate */
			if ( ! array_key_exists($id_backbone, $this->properties->data_package_fields) )
				continue;
				
			// shorthand for data package fields
			$field = $this->properties->data_package_fields[$id_backbone]; // any backbone key
			$data_package = $field['data_package']; // the name of the package, e.g. 'post' or 'author'
			$wp_id = $field['id']; // the wp key name, e.g. post_title, post_parent		

			/* some preprocessing */
			if( isset($field['options']) ) {
				/* this field is read only, so throw away */
				if( array_key_exists('readonly', $field['options']) && $field['options']['readonly'] )
					continue;
				
				/* validate the data */
				if( array_key_exists('validate', $field['options']) ) { 					
					$validate_callback = $field['options']['validate'];
					if(function_exists($validate_callback))
						$value = call_user_func ($validate_callback, $value);	
				}			
			}
			
			/* sort all data in a nice way */
			$parsed[$data_package][$wp_id] = $value;
			/*
		     *	looks something like this now:
			 *	$parsed = array(
			 *   	'post'		=> array( 'post_title' 	=> 'my title',
			 * 							  'post_content'=> 'my content',
			 *							  'any wp key'	=> 'value from backbone'
			 *							),
			 *   	'postmeta'	=> array(...),
			 *   	'comment'	=> array(...)
			 *  );
			 */
			
		}					
		
		/* override some key, values with filter in the child class */
    	$parsed = $this->filter_post_parse_model_request($parsed, $this->request_method);
    	
    	/* there might be an error   	 */
    	if( ! $parsed ) {
	    	$this->set_error( 500, 'request could nt be parsed' );
	    	$parsed = array();
    	}
    	
    	// successfully set our data
    	$this->parsed_model_request = $parsed;	 		
	}
 	
    /**
     * parse_model_response function.
     * 
     * Controller Funtion
     * Prepare and format the model for sending back to backbone
     * used for all requests, but essentially important for read requests, as other request only return the model id 
     * sets $this->parsed_model_response
     *
     * @access protected
     * @final
     * @param false | array $data
     * @return void
     */
    protected final function parse_model_response($data = NULL) {
    	$parsed = false;
		switch ($this->request_method) {
			case 'read':
				$parsed = $this->parse_model_response_read($data);					
			break;				
			case 'create':	
				$parsed = $this->parse_model_response_create($data);					
			break;
			case 'update':
				$parsed = $this->parse_model_response_update($data);					
			break;
			case 'delete':
				$parsed = $this->parse_model_response_delete($data);					
			break;
		}
		
		if($parsed === false) {
			$this->set_error( 355, 'No response was parsed' );
			return;	
		}
		
		$this->parsed_model_response = $parsed;
    }   
      
    /**
     * model_response_parser function.
     * 
     * does the hard work of parsing the reponse
     * no controller logic in this function, only processing
     *
     * @access protected
     * @final
     * @param array $items (e.g. format like the result of $this->_query_all, check there )
     * @param array $data_package_fields, those are all registered fields (e.g. array('title', 'post_title', 'post'))
     * @return array
     */
    protected final function model_response_parser($items, $data_package_fields) {
    	$parsed = array();
    	/* iterate through all data pakages */
    	foreach ( $data_package_fields as $field ) {
    		/* itereate through all passed query items */
    		foreach ($items as $i => $item) {
		   		$key = $field['backbone_id']; // the key specified at backbone, e.g. 'title'
		   		$data_package_name = $item[$field['data_package']]; //the datapackage name, e.g. 'post'
		   		
		   		if ( is_array($data_package_name) ) // only for safty, its always an array
			   		$value = $data_package_name[$field['id']]; // the value e.g. 'my title'
			   	elseif  ( is_object($data_package_name) )
			   		$value = $data_package_name->$field['id'];
			   	else
			   		continue;
			   		  			
		    	$parsed[$i][$key] = $value;
			    /*
			     *	looks something like this now:
				 *	$parsed = array(
				 *   	'title'		=> 'my title'
				 *   	'left	'	=> '345',
				 *   	'parentId'	=> 456
				 *  );
				 */
			}
    	}
    	return $parsed;	    
    }
    
    /**
     * parse_model_response_read function.
     * 
     * parses the model response for read request
     *
     * @access protected
     * @return array
     */
    protected function parse_model_response_read($data) {
	    
	    /* access the unparsed query items  */
/* 	    $items = $this->query; */
	    /* error checks */
	    if( empty($data) ) {
			$this->set_error( 3, 'No items found in database' );
			return;		    
	    }
	    
	    /* parse the query items */
			$parsed = $this->model_response_parser($data, $this->properties->data_package_fields);
			
		/* return a multimensial array when read all models was requested */
    	if($this->id)
			return $parsed[0];
	    else
			return $parsed;
    }
    
	/**
	 * parse_model_response_create function.
	 * 
	 * parse the model response for create requests
	 * results from the create method can be passed (like the new id of the created model)
	 *
	 * @access protected
	 * @param mixed $data
	 * @return mixed
	 */
	protected function parse_model_response_create($id) {   
					
		// use a new instance of this handler to fetch the new saved model
		$handlername = get_class($this);
 		$handler = new $handlername;
 		$handler->read($id);
		$parsed = $handler->get_response();
		return $parsed;	    			        
    }
    
	/**
	 * parse_model_response_update function.
	 * 
	 * parse the model response for update requests
	 * results from the update method can be passed (like the id of the updated model)
	 *
	 * @access protected
	 * @param mixed $data
	 * @return mixed
	 */
	protected function parse_model_response_update($id) {	    
		// use a new instance of this handler to fetch the updated model
		$handlername = get_class($this);
 		$handler = new $handlername;
 		$handler->read($id);
		$parsed = $handler->get_response();
		return $parsed;	      
	}
	
	/**
	 * parse_model_response_delete function.
	 * 
	 * parse the model response for delete requests
	 * results from the delete method can be passed (like the id of the deleted model)
	 *
	 * @access protected
	 * @param mixed $data
	 * @return mixed
	 */
	protected function parse_model_response_delete($id) {	    
		return array($this->properties->id_attribute => $id);	    			        
    }
	
 	/* ===============
	   HELPER
	   =============== */   
	 
	/**
	 * _query_single function.
	 * 
	 * gets a wp object from the database
	 * does additional query specified in the custom_data_package_handlers
	 * adds custom data packages to the result
	 * sets the $this->query
	 *
	 * @TODO datapackage could have the same name as value of the post/comment, prevent this
	 * @access protected
	 * @return array
	 */
	protected function _query_single() {
		
		$item = array();
		/* gets the object according to the classes modelclass from server */
		switch( $this->properties->modelclass ) {
			case('post'):
			case('attachment'):
				$found_item = get_post($this->id);			
				$item['post'] = $found_item;
			break;
			case('comment'):
				$found_item = get_comment( $this->id ); 
				$item['comment'] = $found_item;
			break;
			case('user'):
				$found_item = get_user_by( 'id', $this->id ); 
				$item['user'] = $found_item;			//@TODO
			break;
			case('idone'):
						
				/* get the parsed post data from request */			
				$found_item = 1;
				$item['idone'] = (object) array('idone' => true); // an empty object			
			break;
		}
		if ( ! $found_item ) {
			$this->set_error( 500, 'query returned false' );
			return false;
		}
		
		/* the incoming data from request, pass it to the package data handler, they might be useful there */
		$modelrequest = $this->parsed_model_request;
		
		/* get custom data packkages */
		$custom_data = $this->_action_custom_package_data( $found_item, $modelrequest);
			
		/* merge core data_packages with custom_data packages */
		$item = array_merge($item, $custom_data );	
		/*
	     *	looks something like this now:
		 *	$item = array(
		 *   	'post'				=> array(
		 *									'post_title' => 'My title',
		 *									'post_parent'=> 567,
		 *								),
		 *   	'postmeta'			=> array(...),
		 *  );
		 */			
		
		/* set class var */
		$this->query = array($item); // an  array with one item
		return array($item);
	}	
	
	/**
	 * _query_all function.
	 * 
	 * gets multiple wp objects from the database
	 * does additional query specified in the custom_data_package_handlers
	 * adds custom data packages to the wp objects
	 * sets the $this->query
	 *
	 * @TODO datapackage could have the same name as value of the post/comment, prevent this
	 * @access protected
	 * @return array
	 */
	protected function _query_all() {
				
		/* set the default query args */
		$default_queryargs = array();
		switch( $this->properties->modelclass ) {
			case('idone'):
				$this->set_error( 500, 'One Id modelclass is not supported without id, this error cant even run :)');
				return array();	
			break;
			case('post'):
				$default_queryargs = array(
					'post_type' 		=> 'post',			
					'posts_per_page'	=> -1,
					'orderby'			=> 'ID',
					'order'				=> 'ASC',
					'suppress_filters'	=> false				
				);
			break;
			case('attachment'):
				$default_queryargs = array(
					'post_type' 		=> 'attachment',	
					'post_status'		=> 'any',
					'posts_per_page'	=> -1,
					'orderby'			=> 'ID',
					'order'				=> 'ASC',
					'suppress_filters'	=> false				
				);
			break;
			case('comment'):
				$default_queryargs = array(
					'order'				=> 'ASC',
				);
			break;
			case('user'):
				$default_queryargs = array(
					'role'         => '',
					'meta_key'     => '',
					'meta_value'   => '',
					'meta_compare' => '',
					'meta_query'   => array(),
					'include'      => array(),
					'exclude'      => array(),
					'orderby'      => 'login',
					'order'        => 'ASC',
					'offset'       => '',
					'search'       => '',
					'number'       => '',
					'count_total'  => false,
					'fields'       => 'all',
					'who'          => ''
				);
			break;
		}
		
		/* add request query args */
		$queryargs = array_merge($default_queryargs, $this->request_query_vars);
		
		/* filter args, add more query args from the child class */
		$queryargs = $this->filter_query_args($queryargs);

		// call database	
		$found_items = array();	
		switch( $this->properties->modelclass ) {
			case('post'):
			case('attachment'):
				$found_items = get_posts( $queryargs );
			break;
			case('comment'):
				$found_items = get_comments( $queryargs );
			break;
			case('user'):
				$found_items = get_users($queryargs);
			break;
		}
		
		if ( empty($found_items) ) {
			$this->set_error( 500, 'query is empty' );
			return false;
		}
		
		/* get custom_package data and populate the result */
		$items = array();
		
		for ( $i = 0; $i < count($found_items); $i++ ) {
			switch( $this->properties->modelclass ) {
				case('post'):
				case('attachment'):
					$items[$i]['post'] = $found_items[$i];    			
				break;
				case('comment'):
					$items[$i]['comment'] = $found_items[$i];				
				break;
				case('user'):
					$items[$i]['user'] = $found_items[$i];				
				break;
			}
							
			/* get custom data packkages */
			$custom_data = $this->_action_custom_package_data( $found_items[$i] );			

			/* merge core data_packages with custom_data packages */
			$items[$i] = array_merge($items[$i], $custom_data );			
		}
		/*
	     *	looks something like this now:
		 *	$items = array (
		 *				array(
		 *   				'post' => array(
		 *								'post_title' => 'My title',
		 *								'post_parent'=> 567,
		 *								),
		 *   				'postmeta'			=> array(...),
		 *  			array(...),
		 *			);
		 */
		$this->query = $items;
		return $items;
	}

	/**
	 * _get_data_package_handler function.
	 * 
	 * checks if the data package exists
	 * returns the data_package handler
	 *
	 * @access protected
	 * @param string $data_package_name
	 * @return BB_WP_API_Handler_Data_Package
	 */
	protected function _get_data_package_handler($data_package_name) {
	
		$handlername =  BB_WP_API::_id_to_handler_classname( $data_package_name, 'BB_WP_API_Handler_Data_Package_' ); 
			if ( ! class_exists($handlername) ) {
				$this->set_error( 500, 'Data Package Handler ' . $handlername . ' cant be found' );
				return false;
			}
			
		/* get an instance */
		$handler = new $handlername();
		
		/* do some error checks */			
		if ( ! $handler instanceof BB_WP_API_Handler_Data_Package ) {
			$this->set_error( 500, 'Invalid Data Package Handler' );
			return false;
		}
		
		if ( ! $handler->is_modellclass_allowed($this->properties->modelclass)) {
			$this->set_error( 500, 'Data Package Handler "'. $handlername .'" is not allowed for modelclass ' . $this->properties->modelclass);
			return false;
		}		
		
		return $handler;
	}
	
	/**
	 * _action_custom_package_data function.
	 * 
	 * subject must be a WP object on action read
	 * subject must be an WP object ID on create, update, delete
	 * processes the request with all custom data package handlers
	 * uses the $this->request_method to decide if create, read, update or delete
	 * returns the result from the handlers
	 *
	 * @access protected
	 * @param string $action (default: 'read')
	 * @param int | WP_Post | WP_Comment $subject
	 * @return array
	 */
	protected function _action_custom_package_data($subject, $data = NULL) {
			
		/* get custom data packkages and process them one by one */
		$custom_data = array();	
	    foreach( $this->properties->custom_data_packages as $data_package_name ) {
			$handler = $this->_get_data_package_handler($data_package_name);
			
			if( ! $handler )
				continue;
			
			if($handler) {
				$result = array();				
				switch ($this->request_method) {
					case 'read':
						 $result =$handler->read($subject, $data[$data_package_name]);
					break;				
					case 'create':	
						 $result =$handler->create($subject, $data[$data_package_name]);
					break;
					case 'update':
						 $result =$handler->update($subject, $data[$data_package_name]);
					break;
					case 'delete':
						 $result =$handler->delete($subject);
					break;
				}
				
				/* throw an error is an error object is returned */
				if( is_wp_error($result) ) {
					$this->merge_errors($result);	
				}
				/* prepare the output */
				$custom_data[$data_package_name] = $result;
			}
		}		
		return $custom_data;
	}
	
	/**
	 * _find_in_request function.
	 * 
	 * look for an attribute in model first, the in the root request
	 *
	 * @access protected
	 * @param mixed $attribute
	 * @return void
	 */
	protected function _find_in_request($attribute) {
		
		/* search for a model id, set it when we have one */
		$maybe_value_1 = $this->request['model'][$attribute];
		if( NULL !== $maybe_value_1)
			return $maybe_value_1;
		
		$maybe_value_2 = $this->request[$attribute];		
		if(  NULL !== $maybe_value_2) 
			return $maybe_value_2;	
		
		if(  'idone' == $this->properties->modelclass) 
			return 1; // id_one is allways 1				
	}	

 	/* ===============
	   ABSTRACT
	   =============== */ 
	   
	/**
	 * register_data function.
	 * 
	 * The Child class needs to register data in this method
	 * with the method register_data
	 * use the passed registration object to bind the data to this instance
	 *
	 * @access public
	 * @abstract
	 * @param BB_WP_API_Registration_Unit $register
	 * @return void
	 */
	abstract function register_data($register);	 
}