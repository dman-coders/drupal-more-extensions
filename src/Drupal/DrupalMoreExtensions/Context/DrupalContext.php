<?php

namespace Drupal\DrupalMoreExtensions\Context;

use Behat\Mink\Element\Element;
use Drupal\DrupalExtension\Context\RawDrupalContext;


/**
 * Provides additional API-level step definitions for interacting with Drupal.
 */
class DrupalContext extends RawDrupalContext {


  /**
   * Checks or creates content of the given type.
   *
   * @Given a/an :type (content )with the title :title exists
   */
  public function assumeNode($type, $title) {
    $node = $this->load_node_by_type_and_title($type, $title);
    if (! $node) {
      $node = $this->createNode($type, $title);
    }
    if (! $node) {
      throw new Exception("Failed to create $type with title : $title");
    }
    return $node;
  }

  /**
   * Sets a field on a content item. Item is presumed to already exist.
   *
   * And the "team" titled "Behat Beetles" has a "event" field value of "Behat Trailwalker"
   *
   * @Given the :type titled :title has a(n) :field_name field value of :field_value
   */
  public function ContentHasFieldValue($type, $title, $field_name, $field_value) {
    // @throws not found
    $node = $this->load_node_by_type_and_title ($type, $title);
    $wrapper = entity_metadata_wrapper('node', $node);
    $node->$field_name = array('value' => $field_value);
    node_save($node);
  }


  /**
   * @ingroup utility
   *
   * @param $type
   * @param $title
   * @return bool|mixed
   */
  protected function load_node_by_type_and_title($type, $title) {
    $nid = db_select ('node', 'n')
      ->fields ('n', array ('nid'))
      ->condition ('n.title', $title)
      ->condition ('n.type', $type)
      ->orderBy ('nid', 'desc')
      ->execute ()
      ->fetchField ();
    if (!$nid)
      throw new Exception("'No $type with this title : $title");
    $node = node_load($nid);
    return $node;
  }

}
