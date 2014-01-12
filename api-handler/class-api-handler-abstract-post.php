<?php


/**
 * Abstract BB_WP_API_Request_Handler_Abstract_Post class.
 * 
 * @abstract
 * @extends BB_WP_API_Request_Handler
 */
abstract class BB_WP_API_Handler_Abstract_Post extends BB_WP_API_Handler {
	
	/**
	 * register_data function.
	 * 
	 * @access public
	 * @param BB_WP_API_Registration_Unit $register
	 * @return void
	 */
	public function register_data($register) {
	
		$register->modelclass('post');
		
		$register->data_package('postmeta');
		$register->data_package('author');
		$register->data_package('cap');
		$register->data_package('imagesize');

		
		$register->field_id('id', 'ID', 'post');
		$register->field('title', 'post_title', 'post', array('validate' => 'esc_attr'));
		$register->field('date', 'post_date', 'post', array('readonly' => true ));
		$register->field('authorName', 'display_name', 'author');
		$register->field('authorAvatar', 'avatar', 'author');	
		$register->field('permissionEdit', 'edit', 'cap');
		$register->field('permissionDelete', 'delete', 'cap');
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
				$parsed['post']['post_author'] = $this->current_user->id;
				$parsed['post']['post_status'] = 'publish';
				$parsed['post']['post_type'] = 'post';			
			break;
			case('update'):
			case('delete'):				
			break;
		}		
		return $parsed;
	}
}