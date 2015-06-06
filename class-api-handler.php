<?php


/* a helper class for registering custom API Handler */
require_once ( BB_WP_API_PATH .  '/class-api-registration-unit.php');

/* load the default package handlers */
require_once ( BB_WP_API_PATH .  '/class-api-data-package-handler.php');

/* custom custom API Handler, the are all extensions of this abstrat class */
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
	 * attachment is a wp post with an url 
	 * idone is a special modelclass, without an id (id-one) and without a core data package, for experimental use only
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
	 * the handling of those 3 datapackages in hardcoded
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
	 * query vars sent from backbone collection fetch call
	 * in the same format as WP_Query args
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
	 * this id represents the id of the "model" stored in the database
	 * 
	 * @var int | string
	 * @access protected
	 */
	protected $id = 0;
	
	/**
	 * parent_id
	 * 
	 * some modelclasses like attachments or comments require a parent post to be registered
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
	 * the database results for models are stored here
	 * used for read requests 
	 *
	 * @var array
	 * @access protected
	 */
	protected $query = array();
 	
 	/**
 	 * wp_query
 	 * 
 	 * the instance of the WP_Query
 	 * 
 	 * @var mixed
 	 * @access protected
 	 */
 	protected $wp_query = null;
	/**
	 * parsed_model_response
	 * 
	 * parsed model is stored here, ready for sending to backbone 
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
	 * @depreciated 
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
	 * cache_time
	 * 
	 * overwrite in the extended class
	 * default is 0 , so no caching
	 * 
	 * @var int
	 * @access private
	 */
	protected $cache_time = 0;
	 
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @param array $request 
	 * @return void
	 */
	public function __construct($request = NULL) {
	 		  
	 	/* 
	 	 * register all data for the handler from the extended class
	 	 *		
	 	 * this class handles all settings to be registered from the Extended Handler Class
		 * the registration unit will be instanceld on construct and destroyed immediatly after registration
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
	 	 */
	 	 
	 	/* a valid  modelclass is rerquired */
	 	if( ! $this->properties->modelclass )
			$this->set_error( 55, 'no valid modelclass registered' );			
	 		
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
		
		/* extracts the method, but this is not really importand to do at this point, as it will be set later too */
		/* $this method is also set when the corresponding action ($this-read, create, ...) is called */
		if( isset($request['method']))
			$this->request_method = $request['method'];
		
		/* extract query vars */
		if( isset($request['queryvars']))
			$this->request_query_vars = (array) $request['queryvars']; // array is expected

		/* looking for model ids in the request */
		$this->id = $this->_find_in_request($this->properties->id_attribute);
		$this->parent_id = $this->_find_in_request($this->properties->parent_id_attribute);	
		

		/* USER HANDLING */

	 	/* is the user allowed to make this request  */
	 	if ( ( $this->properties->access == "loggedin" ) && ( ! is_user_logged_in() ) ) {
			$this->set_error( 56, 'user must be logged in for this request' );				 	
	 	}
		
	 	/* some extra authentication */
	 	/* is the uer allowed to make this request  */
 		$allowed = $this->is_authenticated($request, $this->request_method);
 		if(! $allowed)
			$this->set_error( 56, 'user is not authenticated' );				 	
	 		
		
	}


	/**
	 * set_cache function.
	 * 
	 * try to get a cache object
	 *
	 * @access protected
	 * @param mixed $value
	 * @return void
	 */
	protected function set_cache($value) {
    	 set_transient( $this->get_cache_id(), $value, $this->cache_time );     	    	
	}
	
	/**
	 * get_cache function.
	 * 
	 * check strict: return === false
	 *
	 * @access private
	 * @return false or mixed
	 */
	protected function get_cache() {
	
        // uncomment next line to flush previous cache
	    //delete_transient($this->get_cache_id());  
	
    	return get_transient( $this->get_cache_id() );     	   
	}	
	
	/**
	 * get_cache_id function.
	 * 
	 * make an id with the classname
	 *
	 * @access private
	 * @return void
	 */
	private function get_cache_id() {
        $handlername = get_class($this);
        if($this->id)
            $cache_id = '_cache_' . $handlername . '_' . $this->id;
        else 
            $cache_id = '_cache_' . $handlername . '_all';  
        return $cache_id;
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
	 * @param WP_Error $wp_error
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

		/* set method */
		$this->request_method = 'read';
		
		/* read the data from backbone */
		$this->parse_model_request();

		if($this->get_errors())
			return;
				
		/* set id */
		if($id)
			$this->id = $id;

		/* when caching is enabled, try to get the cache */
		if($this->cache_time && false !== $this->get_cache()) {
            $query = $this->get_cache();
            $this->query = $query;

		/* call the database query */
		} else {
    		if($this->id) 
    			$query = $this->_query_single();
    		else
    			$query = $this->_query_all();					    		
		}
			

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
			
		/* set method */
		$this->request_method = 'create';
				
		/* check for a model id */
		if( $this->id) {
			$this->set_error( 4, 'A model id is set in the request, cant create a new item on server' );
			return;			
		}
		
		/* read the data from backbone */
		$this->parse_model_request();

		if($this->get_errors())
			return;
			
		/* get the parsed post data from request */
		$item_data = $this->parsed_model_request;

		/* privileg checking @TODO outsource to filter */
		if( $this->properties->access == "loggedin" && ! current_user_can('edit_posts')) {  // TODO improve CPT
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
				
				/* upload the file ($_FILE) and attach it to the parent post			 */
				$new_id = media_handle_upload('async-upload', $this->parent_id, $attachment);		
			break;
			
			/* comment */
			case('comment'):
				$comment = $item_data['comment'];

				/* a post parent for a comment must be set */			
				if( ! $this->parent_id ) {
					$this->set_error( 7, 'the comment has no parent id' );
					return;
				}
				
				/* in some odd cases it maybe that the parent id is not yet present in the comment array */
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
				$new_id = $this->id; // interacting with the databse is outsourced to the data package handlers			
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

		/* set method */
		$this->request_method = 'update';
				
		/* check for a model id */
		if( ! $this->id) {
			$this->set_error( 10, 'No model id in request, cant update' );
			return;			
		}
		
		/* read the data received from backbone */
		$this->parse_model_request();

		if($this->get_errors())
			return;
					
		/* get the parsed post data from request */
		$item_data = $this->parsed_model_request;
		  			 	
		/* insert database */
		switch( $this->properties->modelclass ) {
		
			/* posts */
			case('post'):
			case('attachment'):
				$post = $item_data['post'];
				
				/* privileg check */ //improve this for coded api calls, like when setting up bootstrap data (for reading it is no problem any way, because this check is only made for update and create and delete)
				if( $this->properties->access == "loggedin" && ! current_user_can('edit_post', $this->id)) {  
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
				
				/* comment updateding is not supported */
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
			
		/* set method */
		$this->request_method = 'delete';
				
		/* check for id */
		if( ! $this->id) {
			$this->set_error( 14, 'No model id in request, cant delete' );
			return;			
		}
		
		/* parse the data from backbone */
		$this->parse_model_request();


		if($this->get_errors())
			return;
					
		/* access the parsed post data from request */
		$item_data = $this->parsed_model_request;		
		  			 	
		/* delete from database */
		switch( $this->properties->modelclass ) {
		
			/* posts */
			case('post'):
			case('attachment'):
				$post = $item_data['post'];
				
				/* privileg check */
				if( $this->properties->access == "loggedin" && ! current_user_can('delete_post', $this->id)) {  
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
				if ( $this->properties->access == "loggedin" && $comment_author != $this->current_user->ID ) {
					$this->set_error( 17, 'This comment can only be deleted by the comment author :' . $comment_author );
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
		
		/* access the prepared model response by the main controllers ($this->read, $this->create, ...) */
		$return = $this->parsed_model_response;
					
		/* add errors to the response if we have any */
		if($this->get_errors())
			$return = $this->get_errors();	
		
		/* when debug is on send the whole instance */
		if($this->debugger) {
			echo (print_r($this));
			die(); 	
		}
		
		/* allow a filter to change or add stuff to the response */
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
  		if( null === $this->wp_query ) {
  			wp_send_json_success($return);  		 		
  		} else {
    		
  			$data = array(
    			'success' => true,
    			'data'    => $return,
    			'query'   => $this->parse_wp_query_object_2_js( $this->wp_query )
  			);
  			wp_send_json($data); // build a custom wp_send_json_success with an extra property 
  		}			
		}
	} 
	
	/* ===============
	   FILTER
	   =============== */

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
	 * filter_found_item function.
	 * 
	 * use this filter for escaping the output
	 *
	 * @access protected
	 * @param mixed $object
	 * @return void
	 */
	protected function filter_found_item($object) {
		return $object;		
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
    	
    	/* get all data packages, also the core packages and parse them */
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
 
    	/* get default values if some are set in the extended handler class */
     	$parsed = $this->filter_pre_parse_model_request($parsed, $this->request_method);
   	
     	/* start parsing */
		foreach ($modeldata as $id_backbone => $value) {
			
			/* check if the data package field is registered */
			/* only data_package_fields registered in backbone pass this gate */
			if ( ! array_key_exists($id_backbone, $this->properties->data_package_fields) )
				continue;
				
			// shorthand for data package fields
			$field = $this->properties->data_package_fields[$id_backbone]; // any backbone key
			$data_package = $field['data_package']; // the name of the package, e.g. 'post' or 'author'
			$wp_id = $field['id']; // the wp key name, e.g. post_title, post_parent		

			/* some preprocessing */
			if( isset($field['options']) ) {
			
				/* this field is read only, so throw it away, we ignore this value */
				if( array_key_exists('readonly', $field['options']) && $field['options']['readonly'] )
					continue;
				
				/* validate the data */
				if( array_key_exists('validate', $field['options']) ) { 					
					$validate_callback = $field['options']['validate'];
					
					/* execute the validation callback e.g. esc_attr(), esc_url(), .. */
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
     * used for all requests, but essentially important for read requests, as other request only return the model id by default
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
    	
    		/* itereate through all passed items */
    		foreach ($items as $i => $item) {
		   		$key = $field['backbone_id']; // the key specified at backbone, e.g. 'title'
		   		$data_package_name = $item[$field['data_package']]; //the datapackage name, e.g. 'post'
		   		
		   		if ( is_array($data_package_name) ) // only for safety, its always an array anyway
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
			
		/* return a multidimensial array when read-all-models was requested or a single array when only one model was requested*/
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
					
		/* use a new instance of this handler to fetch the new saved model */
		/* we could simply return the new model id only to backbone, 
		 * but like this backbone receives the complete wordpress object including all read-only fields also
		 */
		$handlername = get_class($this);
 		$handler = new $handlername;
 		if($this->request) $handler->set_request($this->request); // to make the filters apply
 		// be aware: the method is still 'create' not 'read'
 		$handler->read($id);
 		// now the method was changed to 'read'
		$parsed = $handler->get_response(); // dont send the response, only get it
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
	   
		/* use a new instance of this handler to fetch the updated model */
		$handlername = get_class($this);
 		$handler = new $handlername;
  		if($this->request) $handler->set_request($this->request); // to make the filters apply
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
	
		/* only return the deleted id  */
		return array($this->properties->id_attribute => $id);	    			        
    }
  
  /**
   * parse_wp_query_object_2_js function.
   * 
   * @access protected
   * @param \WP_Query $wpQuery
   * @return void
   */
  protected function parse_wp_query_object_2_js( \WP_Query $wpQuery ) {

    $page = $wpQuery->get( 'page' );
    if( ! $page )
      $page = $wpQuery->get( 'paged' );
    if( ! $page )
      $page = 1;
    
    $offset = $wpQuery->get( 'offset' );
    if( ! $offset )
      $offset = 0;

    $js_object =  array(
      'page'  => $page,
      'pages' => $wpQuery->max_num_pages,
      'count' => $wpQuery->post_count,
      'found' => $wpQuery->found_posts,
      'offset'  => $offset
    );
    
    return $js_object;
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
	 * @TODO datapackage could theoretically have the same name as value of the post/comment, avoid this
	 * @access protected
	 * @return array
	 */
	protected function _query_single() {
		
		$item = array();
		
		/* gets the object according to the modelclass from server */
		switch( $this->properties->modelclass ) {
			case('post'):
			case('attachment'):
				$found_item = get_post($this->id);	
				$found_item = $this->filter_found_item($found_item); // can be used for escaping values
				$item['post'] = $found_item;
			break;
			case('comment'):
				$found_item = get_comment( $this->id ); 
				$found_item = $this->filter_found_item($found_item);
				$item['comment'] = $found_item;
			break;
			case('user'):
				$found_item = get_user_by( 'id', $this->id );
				$found_item = $this->filter_found_item($found_item); 
				$item['user'] = $found_item;			//@TODO
			break;
			case('idone'):
				$found_item = 1; // experimental
				$item['idone'] = (object) array('idone' => true); // an empty object			
			break;
		}
		if ( ! $found_item ) {
			$this->set_error( 500, 'query returned false' );
			return false;
		}
		
		/* the incoming data from request, pass it to the package data handler, the request data might be useful there */
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


		/* when caching is enabled, set the cache */
		if($this->cache_time) {
            $this->set_cache(array($item));
		}
		
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
			case('post'):
				$default_queryargs = array(
					'post_type' 		=> 'post',			
					'posts_per_page'	=> -1,
					'post_status'		=> 'publish',
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
			case('idone'):
				$this->set_error( 500, 'One Id modelclass is not supported without id, this error cant even run :)');
				return array();	
			break;
		}
		
		/* merge in query args from the request */
		$queryargs = array_merge($default_queryargs, $this->request_query_vars);
		
		/* filter args, add more query args from the extended class if desired */
		$queryargs = $this->filter_query_args($queryargs);

		// call database	
		$found_items = array();	
		switch( $this->properties->modelclass ) {
			case('post'):
			case('attachment'):
			  $wpQuery = new \WP_Query($queryargs);
			  $this->wp_query = $wpQuery;
			  if( $wpQuery->have_posts()) {
  				$found_items = $wpQuery->posts;  			  
			  }	  
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
			
			$found_items[$i] = $this->filter_found_item($found_items[$i]);
			
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
		 
		 
		/* when caching is enabled, set the cache */
		if($this->cache_time) {
            $this->set_cache($items);
		}
		
		/* return */
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
	 * subject must be a WP object on action: read
	 * subject must be an WP object-ID on create, update, delete
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
						 $result = $handler->read($subject, $data[$data_package_name]);
					break;				
					case 'create':	
						 $result = $handler->create($subject, $data[$data_package_name]);
					break;
					case 'update':
						 $result = $handler->update($subject, $data[$data_package_name]);
					break;
					case 'delete':
						 $result = $handler->delete($subject);
					break;
				}
				
				/* throw an error if an error object is returned by the handler */
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
	 * look for an attribute in the model request first, then in the root request
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
	
	
	protected function is_authenticated($request, $request_method) {
		
		return true; // override this in your extended class, and throw errors if you want to restrict access
		
		//if(!$request && !$request['key']) {
		//	$this->set_error( '1001', 'No key submitted');
		//	return false; 
		//}
		
		
		
		// this function must be defined in extended class
		//return false; // not authenticated
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