<?php

namespace Drupal\DrupalMoreExtensions\Context;
use Behat\MinkExtension\Context\RawMinkContext;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;

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


  /**
   * @When /^I try to download "([^"]*)"$/
   *
   * The Selenium or Webdriver sessions do not allow us access to the
   * HTTP Response code if we just 'get' a page. Also, getting a PDF
   * Stalls the browser interaction and we get stuck.
   * Instead use iTryToDownload() to retrieve a file and get the headers.
   *
   * https://www.jverdeyen.be/php/behat-file-downloads/
   *
   * Tested with
   * phantomjs 2.1.1,
   * Selenium 2.41.0 + Chrome,
   * guzzlehttp 6.2.0,
   * Goutte 3.1.2.
   *
   * @param string $url
   */
  public function iTryToDownload($url) {
    // Need to make an out-of-band request to the URL in question
    // directly in order to retrieve its headers.
    // Need to sustain cookies, because authentication is important.
    $driver = $this
      ->getSession()
      ->getDriver();
    if (! method_exists($driver, 'getWebDriverSession')) {
      // We are just running goutte or something similar.
      // GET the URL normally and Check the response as usual.
      $driver->visit($url);
      $this->headers = $driver->getResponseHeaders();
      $this->statusCode = $driver->getStatusCode();
      return;
    }

    $cookies = $driver
      ->getWebDriverSession()
      ->getAllCookies();

    // Transfer the browser session cookies into Guzzle ones.
    $jar = new CookieJar();
    foreach ($cookies as $cookie_data) {
      $cookie = new SetCookie($cookie_data);
      // Actually, the keys are expected to be capitalized here....
      $cookie->setName($cookie_data['name']);
      $cookie->setValue($cookie_data['value']);
      $cookie->setDomain($cookie_data['domain']);
      $jar->setCookie($cookie);
    }

    $client = new \GuzzleHttp\Client([
      'base_uri' => $this->getSession()->getCurrentUrl(),
      'cookies' => $jar,
    ]);

    // It's OK if Guzzle throws errors. That's what we are expecting.
    try {
      $response = $client->get($url);
      $this->headers = $response->getHeaders();
      $this->statusCode = $response->getStatusCode();
    } catch (\GuzzleHttp\Exception\ClientException $e) {
      print $e->getMessage();
    }


    // Would be good if we could get the $driver->setStatusCode()
    // so as to let other checks function normally, but that's off-limits.
  }

  /**
   * @Then /^I should see response status code "?(?P<code>\d+)"?$/
   *
   * https://www.jverdeyen.be/php/behat-file-downloads/
   */
  public function iShouldSeeResponseStatusCode($statusCode) {
    $responseStatusCode = $this->statusCode;

    if (!$responseStatusCode == intval($statusCode)) {
      throw new \Exception(sprintf("Did not see response status code %s, but %s.", $statusCode, $responseStatusCode));
    }
  }

  /**
   * @Then /^I should see in the header "([^"]*)":"([^"]*)"$/
   *
   * https://www.jverdeyen.be/php/behat-file-downloads/
   */
  public function iShouldSeeInTheHeader($header, $value) {
    $headers = $this->headers();
    if ($headers->get($header) != $value) {
      throw new \Exception(sprintf("Did not see %s with value %s.", $header, $value));
    }
  }

}
