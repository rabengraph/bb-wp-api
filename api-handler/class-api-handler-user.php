<?php


/**
 * Abstract BB_WP_API_Request_Handler_Abstract_Post class.
 * 
 * @abstract
 * @extends BB_WP_API_Request_Handler
 */
class BB_WP_API_Handler_User extends BB_WP_API_Handler {
	
	/**
	 * register_data function.
	 * 
	 * @access public
	 * @param BB_WP_API_Registration_Unit $register
	 * @return void
	 */
	public function register_data($register) {
	
		$register->modelclass('user');			
		
		$register->data_package('author');		
		$register->field_id('id', 'ID', 'user');
		$register->field('authorName', 'display_name', 'user');
		$register->field('authorAvatar', 'avatar', 'author');			
	}
	
	/**
	 * filter_query_args function.
	 * 
	 * @access public
	 * @param mixed $queryargs
	 * @return void
	 */
	public function filter_query_args($queryargs) {
		
		/* returns the current user only and sets the model id */
		/* change from read_all to read(id) */
		if($queryargs['include'] == 'current') {
			$current_user = wp_get_current_user();
			$queryargs['include'] = array($current_user->ID);
			$this->id = $current_user->ID; 
		}
		return $queryargs;		
	}
	
	/**
	 * filter_pre_parse_model_request function.
	 * 
	 * @access protected
	 * @param mixed $parsed
	 * @param mixed $modelmethod
	 * @return void
	 */
	protected function filter_pre_parse_model_request( $parsed, $modelmethod ) {
		
		switch( $modelmethod ) {
		
			case('create'):
				/* $parsed['post']['post_type'] = 'post'; */
			break;
			case('update'):
			case('delete'):				
			break;
		}		
		return $parsed;
	}
}