<?php

namespace Drupal\DrupalMoreExtensions\Context;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Provides additional Browser session management utilities.
 *
 * Cookies and AJAX can go here.
 * No Drupalisms.
 */
class BrowserContext extends RawMinkContext {

  /**
   * @Given I wait :arg1 seconds
   */
  public function iWaitSeconds($seconds) {
    $this->getSession()->wait((int)$seconds * 1000);
  }


  /**
   * @Given I select vertical tab :arg1
   *
   * Useful on edit forms.
   *
   *   I select vertical tab "#edit-options"
   *
   *   jQuery('#edit-options').data('verticalTab').tabShow()
   */
  public function iSelectVerticalTab($locator) {
    echo "Selecting vertical tab $locator";
    $this->getSession()->executeScript("jQuery('$locator').data('verticalTab').tabShow();");
  }


  /**
   * @Then /^I fill in ckeditor on field "([^"]*)" with "([^"]*)"$/
   *
   * Stolen from https://alfrednutile.info/posts/68 so we can test
   * adding WYSIWYG content
   */
  public function iFillInCKEditorOnFieldWith($arg, $arg2) {
    $this->getSession()->executeScript("CKEDITOR.instances.$arg.setData(\"$arg2\");");
  }

  /**
   * @Then /^I fill in tinymce on field "([^"]*)" with "([^"]*)"$/
   *
   */
  public function iFillInTinyMCEOnFieldWith($locator, $value) {
    $page = $this->getSession()->getPage();
    $field = $page->findField($locator);
    if (null === $field) {
      throw $this->elementNotFound('form field', 'id|name|label|value', $locator);
    }
    $field_id = $field->getAttribute('id');
    $safe_value = addcslashes($value, "'");
    $this->getSession()->executeScript("tinyMCE.getInstanceById(\"$field_id\").setContent(\"$safe_value\");");
  }

  /**
   * @When /^I fill in the "(?P<field>([^"]*))" HTML field with "(?P<value>([^"]*))"$/
   * @When /^I fill in "(?P<value>([^"]*))" for the "(?P<field>([^"]*))" HTML field$/
   *
   * http://behattest.blogspot.co.nz/2014/08/two-ways-to-fill-value-in-html-editor.html
   */
  public function stepIFillInTheHtmlFieldWith($field, $value) {
    $page = $this->getSession()->getPage();
    $field = $page->findField($locator);
    if (null === $field) {
      throw $this->elementNotFound('form field', 'id|name|label|value', $locator);
    }
    $field_id = $field->getAttribute('id');
    $safe_value = addcslashes($value, "'");
    // TODO?
 }

}
