<?php
// $Id$

// Lets call this "fliew", like "fluent_view"
// @see http://en.wikipedia.org/wiki/Fluent_interface

/**
 * @author raphael
 *
 */
class programmed_views {

  /**
   * @var view
   */
  protected $_view = NULL;

  /**
   * @var string
   */
  protected $_type = 'node';

  /**
   * @var array
   */
  protected $_fields = array();

  /**
   * @var array
   */
  protected $_filters = array();

  /**
   * @var array
   */
  protected $_relationships = array();

  /**
   * @var array
   */
  protected $_sorts = array();

  /**
   * @var array
   */
  protected $_outputMapping = array();
  
  /**
   * @param string $type
   */
  public function __construct($type='node') {
    $this->_type = $type;
  }

  /**
   * @param array $args
   * @return array
   */
  protected function _make_values(array $args) {
    $values = array();
    foreach ($args as $arg) {
      if (is_string($arg)) {
        $values[$arg] = $arg;
      }
      else if (is_numeric($arg)) {
        $values[((string) $arg)] = (string) $arg;
      }
      else if (is_array($arg)) {
        $values = array_merge($values, array_combine($arg, $arg));
      }
    }
    return $values;
  }

  /**
   * @param array $args
   * @return string
   */
  protected function _try_getting_relationship(array & $args) {
    $relationship_candidate = (string) reset($args);
    if (isset($this->_relationships[$relationship_candidate . '_nid'])) {
      return array_shift($args) . '_nid';
    }
    else if ('none' == $relationship_candidate) {
      return array_shift($args);
    } 
    else {
      return 'none';
    }
  }
  
  /**
   * @return programmed_views
   */
  public function raw_filter($filter_id, array $config) {
    $this->_filters[(string) $filter_id] = $config;
    return $this;
  }
  
  /**
   * @return programmed_views
   */
  public function raw_relationship($relationship_id, array $config) {
    $this->_relationships[(string) $relationship_id] = $config;
    return $this;
  }
  
  /**
   * @return programmed_views
   */
  public function raw_field($field_id, array $config) {
    $this->_outputMapping[('nid'==$fieldname)?$fieldname:($tablename.'_'.$fieldname)] = $tablename . '_' . $fieldname; //@todo find real mapping
    $this->_fields[(string) $field_id] = $config;
    return $this;
  }
  
  /**
   * @return programmed_views
   */
  public function raw_sort($sort_id, array $config) {
    $this->_sorts[(string) $sort_id] = $config;
    return $this;
  }
  
  /**
   * @return programmed_views
   */
  public function filter_node_type() {
    $args = func_get_args();
    $relationship = $this->_try_getting_relationship($args);
    $this->_filters['type'] = array(
      'operator' => 'in',
      'value' => $this->_make_values($args),
      'id' => 'type',
      'table' => 'node',
      'field' => 'type',
      'relationship' => $relationship,
    );
    return $this;
  }
  
  /**
   * @return programmed_views
   */
  public function filter_nodereference_field() {
    $args = func_get_args();
    $fieldname = array_shift($args);
    $relationship = $this->_try_getting_relationship($args);
    $this->_filters[$fieldname . '_nid'] = array(
      'operator' => 'or',
      'value' => $this->_make_values($args),
      'id' => $fieldname . '_nid',
      'table' => 'node_data_' . $fieldname,
      'field' => $fieldname . '_nid',
      'relationship' => $relationship,
    );
    return $this;
  }

  /**
   * @return programmed_views
   */
  public function filter_userreference_field() {
    $args = func_get_args();
    $fieldname = array_shift($args);
    $relationship = $this->_try_getting_relationship($args);
    $this->_filters[$fieldname . '_uid'] = array(
      'operator' => 'or',
      'value' => $this->_make_values($args),
      'id' => $fieldname . '_uid',
      'table' => 'node_data_' . $fieldname,
      'field' => $fieldname . '_uid',
      'relationship' => $relationship,
    );
    return $this;
  }
  
  /**
   * @return programmed_views
   */
  public function filter_published() {
    $this->_filters['status'] = array(
      'operator' => '=',
      'value' => '1',
      'id' => 'status',
      'table' => 'node',
      'field' => 'status',
    );
    return $this;
  }

  /**
   * @return programmed_views
   */
  public function filter_cck_field($fieldname, $value) {
    $this->_filters[$fieldname] = array(
      'operator' => '=',
      'value' => array('value' => $value),
      'id' => 'field_' . $fieldname . '_value',
      'table' => 'node_data_field_' . $fieldname,
      'field' => 'field_' . $fieldname . '_value',
    );
    return $this;
  }

  /**
   * @return programmed_views
   */
  public function relationship_nodereference_field($fieldname, $relationship='none') {
    $this->_relationships[$fieldname . '_nid'] = array(
      'required' => 1,
      'delta' => '-1',
      'id' => $fieldname . '_nid',
      'table' => 'node_data_' . $fieldname,
      'field' => $fieldname . '_nid',
      'relationship' => $relationship . (('none'==$relationship)?'':'_nid'),
    );
    return $this;
  }

  /**
   * @return programmed_views
   */
  public function relationship_content_profile($content_type, $relationship='none') {
    $this->_relationships['content_profile_rel'] = array(
      'required' => 1,
      'type' => $content_type,
      'id' => 'content_profile_rel',
      'table' => 'users',
      'field' => 'content_profile_rel',
//      'relationship' => $relationship,
      'relationship' => $relationship . (('none'==$relationship)?'':'_nid'),
    );
    return $this;
  }

  /**
   * @return programmed_views
   */
  public function sort_changed_newest_first() {
    return $this->sort_changed('DESC');
  }
  
  /**
   * @return programmed_views
   */
  public function sort_changed_oldest_first() {
    return $this->sort_changed('ASC');
  }
  
  /**
   * @return programmed_views
   */
  public function sort_changed($direction='ASC') {
    $direction = ('ASC'==$direction) ? 'ASC' : 'DESC';
    $this->_sorts['changed'] = array(
      'order' => $direction,
      'granularity' => 'second',
      'id' => 'changed',
      'table' => 'node',
      'field' => 'changed',
      'relationship' => 'none',
    );
    return $this;
  }
  
  /**
   * @param string $fieldname
   * @return programmed_views
   */
  public function field($tablename, $fieldname, $relationship='none') {
    $this->_outputMapping[('nid'==$fieldname)?$fieldname:($tablename.'_'.$fieldname)] = $tablename . '_' . $fieldname; //@todo find real mapping
    $this->_fields[$tablename . '_' . $fieldname] = array(
      'hide_empty' => 0,
      'empty_zero' => 0,
      'exclude' => 0,
      'id' => $fieldname,
      'table' => $tablename,
      'field' => $fieldname,
      'relationship' => $relationship . (('none'==$relationship)?'':'_nid'),
    );
    return $this;
  }
  
  /**
   * @param string $fieldname
   * @return programmed_views
   */
  public function node_field($fieldname, $relationship='none') {
//    $this->_outputMapping[('nid'==$fieldname)?$fieldname:('node_'.$fieldname)] = $fieldname;
    $this->_fields[$fieldname] = array(
      'hide_empty' => 0,
      'empty_zero' => 0,
      'exclude' => 0,
      'id' => $fieldname,
      'table' => 'node',
      'field' => $fieldname,
      'relationship' => $relationship . (('none'==$relationship)?'':'_nid'),
    );
    return $this;
  }
  
  /**
   * @param string $fieldname
   * @return programmed_views
   */
  public function user_field($fieldname, $relationship='none') {
    $this->_fields[$fieldname] = array(
      'hide_empty' => 0,
      'empty_zero' => 0,
      'exclude' => 0,
      'id' => $fieldname,
      'table' => 'users',
      'field' => $fieldname,
      'relationship' => $relationship,
    );
    return $this;
  }
  
  /**
   * @param string $fieldname
   * @return programmed_views
   */
  public function field_nodereference($fieldname, $relationship='none') {
    $this->_outputMapping['node_data_' . $fieldname . '_' . $fieldname . '_nid'] = preg_replace('/^field_/', '', $fieldname) . '_nid';
    $this->_fields[$fieldname . '_nid'] = array(
      'hide_empty' => 0,
      'empty_zero' => 0,
      'multiple' => array(
        'group' => TRUE,
        'multiple_number' => '',
        'multiple_from' => '',
        'multiple_reversed' => FALSE,
      ),
      'exclude' => 0,
      'id' => $fieldname . '_nid',
      'table' => 'node_data_' . $fieldname,
      'field' => $fieldname . '_nid',
      'relationship' => $relationship . (('none'==$relationship)?'':'_nid'),
      );
    return $this;
  }

  public function cck_string_field($fieldname, $relationship='none') {
    $this->_fields[$fieldname] = array(
      'id' => 'field_' . $fieldname . '_value',
      'table' => 'node_data_field_' . $fieldname,
      'field' => 'field_' . $fieldname . '_value',
      'relationship' => $relationship,
    );
    return $this;
  }
  
  /**
   * @param string $fieldname
   * @return programmed_views
   */
  public function field_string($fieldname, $relationship='none') {
    return $this->field_number($fieldname, $relationship);
  }
  
  /**
   * @param string $fieldname
   * @return programmed_views
   */
  public function field_number($fieldname, $relationship='none') {
    $this->_outputMapping['node_data_' . $fieldname . '_' . $fieldname . '_value'] = preg_replace('/^field_/', '', $fieldname);
    $this->_fields[$fieldname . '_value'] = array(
      'id' => $fieldname . '_value',
      'table' => 'node_data_' . $fieldname,
      'field' => $fieldname . '_value',
      'relationship' => $relationship . (('none'==$relationship)?'':'_nid'),
    );
    return $this;
  }
  
  /**
   * @return array
   */
  public function exec() {
    $this->_view = views_new_view();
    $this->_view->view_php = '';
    $this->_view->base_table = $this->_type;
    $this->_view->is_cacheable = FALSE;
    $this->_view->api_version = 2;
    $handler = $this->_view->new_display('default', 'Defaults', 'default');
    $this->_view->display['default']->handler->override_option('items_per_page', 0);
    $this->_view->display['default']->handler->override_option('relationships', $this->_relationships);
    $this->_view->display['default']->handler->override_option('fields', $this->_fields);
    $this->_view->display['default']->handler->override_option('sorts', $this->_sorts);
    $this->_view->display['default']->handler->override_option('filters', $this->_filters);
    $dummy = $this->_view->execute_display('default', array());

    $mapping = array();
    foreach($this->_view->display_handler->handlers['field'] as $field_id => $fieldhandler) {
      $mapping[$fieldhandler->field_alias] = $field_id;
    }
//    var_dump($mapping);
    
    
//    var_dump($this->_view);
//    var_dump($this->_view->result);
//    var_dump($this->_outputMapping);
//    exit;
    
    $output = array();
    foreach ($this->_view->result as $row) {
      $newRow = new stdClass;
//      foreach ($this->_outputMapping as $viewFieldName => $outputFieldName) {
      foreach ($mapping as $viewFieldName => $outputFieldName) {
        $newRow->{$outputFieldName} = $row->{$viewFieldName};
      }
      if (isset($newRow->nid)) {
        $output[$newRow->nid] = $newRow;
//        $output[$newRow->nid] = $row;
      }
      else {
        $output[] = $newRow;
//        $output[] = $row;
      }
    }
    return $output;
  }
  
}
