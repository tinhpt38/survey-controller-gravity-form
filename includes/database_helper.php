<?php


class DatabaseHelper
{

  private $table_name;

  public function __construct()
  {
    global $wpdb;
    $this->table_name = $wpdb->prefix . 'udoo_gf_map_author';
  }

  public function insert($form_id, $user_id)
  {
    global $wpdb;
    $newid = $wpdb->insert($this->table_name, array(
      'form_id' => $form_id,
      'user_id' => $user_id,
    ));
    return $newid;
  }

  public function delete($form_id)
  {
    global $wpdb;
    $wpdb->delete($this->table_name, array(
      'form_id' => $form_id,
    ));
  }

  public function get($form_id)
  {
    global $wpdb;
    $sql = "SELECT * FROM {$this->table_name} WHERE form_id = {$form_id}";
    return $wpdb->get_results($sql);
  }

  public function get_by_user($id){
    global $wpdb;
    $sql = "SELECT * FROM {$this->table_name} WHERE user_id = {$id}";
    return $wpdb->get_results($sql);
  }

  public function update($form_id, $user_id)
  {
    global $wpdb;
    $result = $this->get($form_id);
    if(empty($result)){
      $this->insert($form_id, $user_id);
    }else{
      $wpdb->update(
        $this->table_name,
        array('user_id' => $user_id),
        array('form_id' => $form_id),
      );
    }
  }
}
