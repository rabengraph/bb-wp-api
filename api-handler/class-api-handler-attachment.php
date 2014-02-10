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
		
		// overwrite
		$register->modelclass('attachment');
		$register->data_package('embedder');

		$register->field_parent_id('parentId', 'post_parent', 'post', array('readonly' => false ));
		
		$register->field('url', 'guid', 'post', array('readonly' => false ));
		$register->field('mime', 'post_mime_type', 'post', array('readonly' => true ));	
		$register->field('html', 'html', 'embedder', array('readonly' => true ));
		$register->field('embed', 'embed', 'embedder', array('readonly' => true ));
		$register->field('type', 'type', 'embedder', array('readonly' => true ));
		$register->field('frame', 'frame', 'embedder', array('readonly' => true ));

		

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
/*         	'post_mime_type' 	=> 'image', */
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
	
		switch( $modelmethod) {
			case('create'):					
				$parsed['post']['post_type'] = 'attachment';
				$parsed['post']['post_status'] = 'inherit';					
			break;
		}
		return $parsed;
	}	
}