<?php

/**
 * BB_WP_API_Request_Handler_Attachment class.
 * 
 * @extends BB_WP_API_Request_Handler_Abstract_Post
 */
class BB_WP_API_Handler_Attachment extends BB_WP_API_Handler_Abstract_Post {
	
	/**
	 * register_data function.
	 * 
	 * @access public
	 * @param BB_WP_API_Registration_Unit $register
	 * @return void
	 */
	public function register_data($register) {
		
		parent::register_data($register);
		
		$register->field('url', 'guid', 'post', array('readonly' => true ));
		$register->field('parentId', 'post_parent', 'post', array('readonly' => true ));
		$register->field('thumbUrl', 'thumburl', 'imagesize', array('readonly' => true ));		
	}
	
	/**
	 * filter_query_args function.
	 * 
	 * @access protected
	 * @param mixed $default_queryargs
	 * @return void
	 */
	protected function filter_query_args($queryargs) {

		$my_queryargs = array(
        	'post_mime_type' 	=> 'image',
			'post_type' 		=> 'designrevisions',	
		    'post_type'   => 'any', 
			'post_status' => 'any'
		);		
		
		return array_merge( $queryargs, $my_queryargs );
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
				$parsed['post']['post_type'] = 'attachment';				
			break;
		}
		return $parsed;
	}
	
	/**
	 * filter_post_parse_model_request function.
	 * 
	 * set override values
	 *
	 * @access protected
	 * @param mixed $parsed
	 * @param mixed $modelmethod
	 * @return void
	 */
	protected function filter_post_parse_model_request($parsed, $modelmethod) {
		
		$parsed = parent::filter_pre_parse_model_request($parsed, $modelmethod);
		
		switch( $modelmethod ) {
			case('create'):
				if( ! isset($parsed['post']['post_parent'])) {
					$this->errors->add( 5, 'the attachment has no parent id' );
					return false;
				}				
			break;
		}		
		return $parsed;
	}		
}