<?php



class BB_WP_API_Handler_Loginuser extends BB_WP_API_Handler_Abstract_Post {
	

	/**
	 * register_data function.
	 * 
	 * @access public
	 * @param BB_WP_API_Registration_Unit $r
	 * @return void
	 */
	public function register_data($r) {
		
		$r->modelclass('idone');		
		$r->data_package('loginuser_meta');
		$r->access('all');
/* 		$r->field_id('id', 'idone', 'loginuser_meta'); */
		$r->field('username', 'user_login', 'loginuser_meta', array('validate' => 'esc_attr'));
		$r->field('pass', 'user_password', 'loginuser_meta');

	}
		
}