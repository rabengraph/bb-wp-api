<?php


/**
 * BB_WP_API_Request_Handler_Review class.
 * 
 * @extends BB_WP_API_Request_Handler_Abstract_Post
 */
class BB_WP_API_Handler_Review extends BB_WP_API_Handler_Abstract_Post {
	

	/**
	 * register_data function.
	 * 
	 * @access public
	 * @param BB_WP_API_Registration_Unit $register
	 * @return void
	 */
	public function register_data($register) {
		
		parent::register_data($register);
		
		$register->field('progress', 'progress', 'postmeta');
		$register->field('associatedUrl', 'associated_url', 'postmeta', array('validate' => 'esc_url'));
		$register->field('left', 'left', 'postmeta');
		$register->field('top', 'top', 'postmeta');
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
			/* 'meta_key'     	    => 'associated_url',  */
			/* 'meta_value'   	    => $this->request_url, */
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
		
		$parsed = parent::filter_pre_parse_model_request($parsed, $modelmethod);
		
		switch( $modelmethod ) {
			case('create'):
				$parsed['post']['post_type'] = 'designrevisions';				
			break;
		}
		
		return $parsed;
	}
	
	/**
	 * filter_response function.
	 * 
	 * @access public
	 * @param mixed $return
	 * @return array
	 */
	public function filter_response($return) {
		
		/* when we got errors all is lost already */
		if($this->get_errors())
			return $return;
				
		/* only on read */
		if(	$this->request_method != 'read' )
			return $return;	
		
		/* single review */
		if($this->id) {
		
			$review = $return;
			$review = $this->_append_comments_and_attachments($review);
			$return = $review;
		
		/* if all models are requested	 */
		} else {
		
			$reviews = $return;
			$appended_reviews = array();	
			foreach ($reviews as $review) {
				$appended_reviews[] = $this->_append_comments_and_attachments($review);			
			} 
			$return = $appended_reviews;	
		}
		
		/* send the current user */
		/* this part is a little hack, like this the current user is sent too */
		
/*
		$user_handler = new BB_WP_API_Handler_Data_Package_Author;
		$current_userdata = $user_handler->read(wp_get_current_user());
		$data[]['author'] = $current_userdata;
		$current_userdata = $this->model_response_parser($data, $this->properties->data_package_fields);
		$return['currentUser'] = $current_userdata[0];
		
*/
		return $return;		
	}
	
	/**
	 * _append_comments_and_attachments function.
	 * 
	 * helper
	 * adds comments and attachments of the review to the review
	 *
	 * @access protected
	 * @param mixed $review
	 * @return void
	 */
	protected function _append_comments_and_attachments($review) {
		
		/* send all comments */
		$comment_handler = new BB_WP_API_Handler_Comment();
		$comment_request = array(
			'method'	=> 'read',
			'queryvars'	=> array('post_id' => $review[$this->properties->id_attribute])
		);
		$comment_handler->set_request($comment_request);
		$comment_handler->read();
		$review['comments'] = $comment_handler->parsed_model_response;
		
		/* send all attachments */
		$attachment_handler = new BB_WP_API_Handler_Attachment();
		$attachment_request = array(
			'method'	=> 'read',
			'queryvars'	=> array('post_parent' => $review[$this->properties->id_attribute])
		);
		$attachment_handler->set_request($attachment_request);
		$attachment_handler->read();
		$review['attachments'] = $attachment_handler->parsed_model_response;
		
		return $review;	
	}	
}