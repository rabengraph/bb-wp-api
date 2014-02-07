<?php

/**
 * BB_WP_API_Registration_Unit class.
 *
 * used to register modelclass, id_attribute, datapackages and datapackagefields to the api handler
 */
class BB_WP_API_Registration_Unit {
	
	/**
	 * modelclasses
	 * 
	 * received from the API Handler
	 * 
	 * @var array
	 * @access private
	 */
	private $modelclasses = array();

	/**
	 * core_data_packages
	 * 
	 * received from the API Handler
	 * 
	 * @var array
	 * @access private
	 */
	private $core_data_packages = array();
	
	/**
	 * properties
	 * 
	 *
	 * modelclass: supported model classes
	 * id_attribute :
	 * custom_data_packages: will be populated with names of registered data packages
	 * data_package_fields: array (bb_id, wp_id, custom_data_package, options)
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $properties = array(
		
		'modelclass' 			=> '',
		'id_attribute'			=> 'ID',
		'custom_data_packages'	=> array(),
		'data_package_fields'	=> array(),
		'access'				=> "loggedin"
	);
	
	
	function __construct( $options ) {
		if( ! isset($options['core_data_packages']) || ! isset($options['modelclasses']) )
			return false;
	
		$this->properties = (object) $this->properties;
		$this->core_data_packages = $options['core_data_packages'];
		$this->modelclasses = $options['modelclasses'];
	}

	/**
	 * modelclass function.
	 * 
	 * registers the modelclass
	 *
	 * @access public
	 * @param string $modelclass
	 * @return void
	 */
	public function modelclass( $modelclass ) {	
		if( in_array( $modelclass, $this->modelclasses ))
			$this->properties->modelclass = $modelclass; 	
	}

	/**
	 * data_package function.
	 * 
	 * registers a data_package
	 *
	 * @access public
	 * @final
	 * @param string $data_package
	 * @return void
	 */
	public final function data_package($data_package) {
		if( ! in_array( $data_package, $this->properties->custom_data_packages ))
			$this->properties->custom_data_packages[] = $data_package;
	}
		
	/**
	 * field function.
	 * 
	 * registers the data_package_field
	 *
	 * @access public
	 * @final
	 * @param string $id_backbone
	 * @param string $id_wp
	 * @param string $data_package
	 * @param array $options (default: NULL)
	 * @return void
	 */
	public final function field( $id_backbone, $id_wp, $data_package, $options = NULL) {
		
		/* inspect the input */
		$backbone_id = esc_attr($id_backbone);
		$id_wp = esc_attr($id_wp);
		$data_package = ( $this->_data_package_exists($data_package) ) ? $data_package : false;		
		if ( ! $id_backbone || ! $id_wp || ! $data_package )
			return false;
		
		/* parse the registered field	 */
		$field = array('backbone_id' => $id_backbone, 'id' => $id_wp, 'data_package' => $data_package);
			if( isset($options) && is_array($options))
				$field['options'] = $options;
				
		/* collect in the array */
		$this->properties->data_package_fields[$id_backbone] = $field; 
			return 1;		
	}
	
	/**
	 * field_id function.
	 * 
	 * registers the model id attribute
	 *
	 * @access public
	 * @final
	 * @param string $id_backbone
	 * @param string $id_wp
	 * @param string $data_package
	 * @param array $options (default: NULL)
	 * @return void
	 */
	public final function field_id( $id_backbone, $id_wp, $data_package, $options = NULL) {
		
		$registered = $this->field($id_backbone, $id_wp, $data_package, $options = NULL);	
		
		/* set the ID Attribute */
		if($registered) {
			$this->properties->id_attribute =  esc_attr($id_backbone);
		}	
	}
	
	
	/**
	 * field_parent_id function.
	 * 
	 * register the model parent id attribute
	 *
	 * @access public
	 * @final
	 * @param mixed $id_backbone
	 * @param mixed $id_wp
	 * @param mixed $data_package
	 * @param mixed $options (default: NULL)
	 * @return void
	 */
	public final function field_parent_id( $id_backbone, $id_wp, $data_package, $options = NULL) {
		
		$registered = $this->field($id_backbone, $id_wp, $data_package, $options = NULL);	
		
		/* set the ID Attribute */
		if($registered) {
			$this->properties->parent_id_attribute =  esc_attr($id_backbone);
		}	
	}

	/**
	 * access function.
	 * 
	 * api accessable for logged in users only or for all
	 *
	 * @access public
	 * @final
	 * @param mixed $access
	 * @return void
	 */
	public final function access( $access ) {
		
		$access_whitelist = array('loggedin', 'all');
		if(in_array($access, $access_whitelist)) {
			$this->properties->access = $access;		
		}
	}
	
	/**
	 * get_properties function.
	 * 
	 * recieve all registered data
	 *
	 * @access public
	 * @return void
	 */
	public function get_properties() {
		return $this->properties;
	}
	
	/**
	 * _data_package_exists function.
	 * 
	 * check if a data_package already exists
	 *
	 * @access private
	 * @param mixed $data_package
	 * @return void
	 */
	private function _data_package_exists($data_package) {
		if( in_array( $data_package, $this->core_data_packages ))
			return true;
		if( in_array( $data_package, $this->properties->custom_data_packages ))
			return true;
	
		return false;		
			
	}	
}