<?php

/**
 * BB_WP_API_Request_Handler_Comment class.
 * 
 * @extends BB_WP_API_Request_Handler
 */
class BB_WP_API_Handler_Comment extends BB_WP_API_Handler {
	
	/**
	 * register_data function.
	 * 
	 * @access public
	 * @param BB_WP_API_Registration_Unit $register
	 * @return void
	 */
	public function register_data($register) {
	
		$register->modelclass('comment');
		
		
		$register->data_package('author');
		$register->data_package('cap');
		$register->data_package('nonces');
		
		
		$register->field_id('id', 'comment_ID', 'comment');		
		$register->field_parent_id('parentId', 'comment_post_ID', 'comment');		

		$register->field('content', 'comment_content', 'comment');
		$register->field('date', 'comment_date', 'comment', array('readonly' => true ));
		$register->field('authorName', 'display_name', 'author');
		$register->field('authorAvatar', 'avatar', 'author');	
		$register->field('permissionEdit', 'edit', 'cap');
		$register->field('permissionDelete', 'delete', 'cap');
	}

	/**
	 * filter_found_item function.
	 * 
	 * @access protected
	 * @return void
	 */
	protected function filter_found_item($object) {
	
		/* escape content */
		$object->comment_content =  wp_kses($object->comment_content, wp_kses_allowed_html( 'strip' ));;		
		return $object;
	}
	
	/**
	 * filter_query_args function.
	 * 
	 * @access protected
	 * @param mixed $queryargs
	 * @return void
	 */
	protected function filter_query_args($queryargs) {

		$my_queryargs = array(
			'post_type' 		=> 'designrevisions',
		);				
		return array_merge( $queryargs, $my_queryargs ) ;
	}
		
	/**
	 * filter_pre_parse_model_request function.
	 * 
	 * set default values
	 *
	 * @access protected
	 * @param mixed $parsed
	 * @param mixed $modelmethod
	 * @return void
	 */
	protected function filter_pre_parse_model_request($parsed, $modelmethod) {
				
		switch( $modelmethod ) {
			case('create'):
				$parsed['comment']['user_id'] = $this->current_user->id;			
			break;
			case('update'):		
			break;
		}	
		return $parsed;
	}	
}