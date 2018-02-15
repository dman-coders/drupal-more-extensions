<?php

namespace Drupal\DrupalMoreExtensions\Context;

use Behat\Mink\Element\Element;
use Drupal\DrupalExtension\Context\RawDrupalContext;


/**
 * Provides additional API-level step definitions for interacting with Drupal.
 */
class DrupalContext extends RawDrupalContext {




  /**
   * Goes to a page based on content type and title
   *
   *
   * @When I go to the page for the :type with the title :title
   *
   * @throws \Exception if named content not found
   */
  public function goToNamedContent($type, $title) {
    $node = $this->load_node_by_type_and_title($type, $title);
    $this->getSession()->visit($this->locatePath('/node/' . $node->nid));
  }


  /**
   * Checks or creates content of the given type.
   *
   * @Given a/an :type (content )with the title :title exists
   */
  public function assumeNode($type, $title) {
    $node = $this->load_node_by_type_and_title($type, $title, FALSE);
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
   * And the "team" with the title "Behat Beetles" has a "event" field value of "Behat Trailwalker"
   *
   * @Given the :type with the title :title has a(n) :field_name field value of :field_value
   */
  public function ContentHasFieldValue($type, $title, $field_name, $field_value) {
    // @throws not found
    $node = $this->load_node_by_type_and_title ($type, $title);
    $wrapper = entity_metadata_wrapper('node', $node);
    // Only trigger save if needed.
    if ($field_value != $node->$field_name) {
      $node->$field_name = $field_value;
      node_save($node);
    }
  }

  /**
   * Sets a flag on a content item. Item is presumed to already exist.
   *
   * And the "team" with the title "Behat Beetles" has the "promoted" flag "on"
   *
   * @Given the :type with the title :title has the :field_name flag (set )$field_value
   */
  public function ContentHasFlagSet($type, $title, $field_name, $field_value) {
    $string_values = array(
      'off' => 0,
      'on' => 1,
    );
    if (isset($string_values[$field_value])) $field_value = $string_values[$field_value];
    return $this->ContentHasFieldValue($type, $title, $field_name, $field_value);
  }


  /**
   * @ingroup utility
   *
   * @param $type
   * @param $title
   * @return bool|mixed
   *
   * @throws \Exception if named content not found
   */
  protected function load_node_by_type_and_title($type, $title, $complain = TRUE) {
    $nid = db_select ('node', 'n')
      ->fields ('n', array ('nid'))
      ->condition ('n.title', $title)
      ->condition ('n.type', $type)
      ->orderBy ('nid', 'desc')
      ->execute ()
      ->fetchField ();
    if (!$nid && $complain)
      throw new Exception("'No $type with this title : $title");
    $node = node_load($nid);
    return $node;
  }

}
