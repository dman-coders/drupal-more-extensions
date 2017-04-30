<?php

namespace Drupal\DrupalMoreExtensions\Context;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Mink\Exception\ElementNotFoundException;
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
   * HTTP status of the last request.
   *
   * @var string
   */
  protected $statusCode;

  /**
   * HTTP headers from the last request.
   *
   * @var string[]
   */
  protected $headers;

  /**
   * Wait a bit.
   *
   * @Given I wait :arg1 seconds
   */
  public function iWaitSeconds($seconds) {
    $this->getSession()->wait((int) $seconds * 1000);
  }


  /**
   * Checks, that element with specified CSS contains specified attribute.
   *
   * MinkExtension provides it almost, but does not expose it.
   *
   * @see Behat\MinkExtension\Context::assertElementContains
   * @see Drupal\DrupalExtension\Context::assertRegionElementAttribute
   *
   * @Then /^the "(?P<element>[^"]*)" element should contain "(?P<value>(?:[^"]|\\")*)" in its "(?P<attribute>(?:[^"]|\\")*)"( attribute)?$/
   * @Then I( should) see the :element element with the :attribute attribute set to :value
   */
  public function assertAttributeContains($element, $value, $attribute) {
    $this->assertSession()->elementAttributeContains('css', $element, $attribute, $value);
  }
  
  /**
   * @see http://stackoverflow.com/questions/33649518/how-can-i-click-a-span-in-behat
   *
   * click() may require Javascript if applied to non-"a" elements.
   *
   * @Given I click the :arg1 element
   */
  public function iClickTheElement($locator) {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', $locator);
    if (empty($element)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'pattern', $locator);
    }
    $element->click();
  }

  /**
   * Select a tab.
   *
   * @Given I select vertical tab :arg1
   *
   * Useful on edit forms.
   *
   *   I select vertical tab "#edit-options"
   *
   *   jQuery('#edit-options').data('verticalTab').tabShow()
   */
  public function iSelectVerticalTab($locator) {
    // If no js, the selection doesn't need to happen,
    // So just ignore the exception that complains that js is not available.
    $page = $this->getSession()->getPage();
    // The .vertical-tabs class only appears if javascript has run.
    if ($page->find('css', ".vertical-tabs " . $locator)) {
      echo "Selecting vertical tab $locator.";
      $this->getSession()->executeScript("jQuery('$locator').data('verticalTab').tabShow();");
    }
    else {
      echo "No vertical tab $locator. Assuming we are running no-js and all is well anyway.";
    }
  }

  /**
   * Add text to WYSIWYG.
   *
   * Stolen from https://alfrednutile.info/posts/68 so we can test
   * adding WYSIWYG content.
   *
   * @param string $locator
   *   Field ID (or name, css selector or xpath).
   * @param string $value
   *   Form field content.
   *
   * @Then /^I fill in ckeditor on field "([^"]*)" with "([^"]*)"$/
   * @When I fill in the :locator ckeditor field with :value
   */
  public function FillCkEditorField($locator, $value) {
    $this->getSession()->executeScript("CKEDITOR.instances.$locator.setData(\"$value\");");
  }

  /**
   * Add text to WYSIWYG.
   *
   * @param string $locator
   *   Field ID (or name, css selector or xpath).
   * @param string $value
   *   Form field content.
   *
   * @Then /^I fill in tinymce on field "([^"]*)" with "([^"]*)"$/
   * @When I fill in the :locator tinymce field with :value
   */
  public function FillTinyMceField($locator, $value) {
    $page = $this->getSession()->getPage();
    $field = $page->findField($locator);
    if (NULL === $field) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'tinymce field', 'id|name|label|value|placeholder', $locator);
    }
    $field_id = $field->getAttribute('id');
    $safe_value = addcslashes($value, "'");
    $this->getSession()->executeScript("tinyMCE.getInstanceById(\"$field_id\").setContent(\"$safe_value\");");
  }

  /**
   * Fills in form field with specified id|name|label|value
   *
   * Private copy of minkContext version, just so I can use $this
   * inside fillWYSIWYGField().
   */
  private function fillField($field, $value) {
    $this->getSession()->getPage()->fillField($field, $value);
  }

  /**
   * Set value of a WYSIWYG field.
   *
   * This needs to work both with javascript on and off!
   * With no js, adding content to the textfield works normally.
   * With JS, we need to address the WYSIWYG libraries.
   *
   * @param string $locator
   *   Field ID (or name, css selector or xpath).
   * @param string $value
   *   Form field content.
   *
   * @return bool

   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @see \Behat\MinkExtension\Context\MinkContext::fillField()
   *
   * @When I fill in the :locator WYSIWYG field with :value
   */
  public function fillWYSIWYGField($locator, $value) {
    // We need to first correctly identify the ID of something
    // that may have been found by a locator pattern.
    // Further logic requires the ID specifically.
    $page = $this->getSession()->getPage();
    $field = $page->findField($locator);
    if (NULL === $field) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'form field', 'id|name|label|value|placeholder', $locator);
    }
    $field_id = $field->getAttribute('id');
    $safe_value = addcslashes($value, "'");

    // See if we can operate on either ckeditor or tinymce here.
    // Do not look-ahead, just try everything and ignore the consequences.
    // Ignore any fails if we eventually succeed.
    $success = FALSE;
    $failures = array();
    foreach (array('fillTinyMceField', 'FillCkEditorField', 'fillField') as $callback) {
      try {
        $this->$callback($locator, $value);
        // Errors may have happened ^ . But if not:
        $success = TRUE;
        return $success;
      }
      catch (\Exception $e) {
        // Failure is always an option.
        $failures[$callback] = $e;
      }
    }
    // Summarize all fails if nothing succeeded.
    $message = "Failed to communicate with WYSIWYG libraries\n";
    foreach ($failures as $callback => $fail) {
      $message .= "Error from attempting $callback:\n" . $fail->getMessage() . "\n";
    }
    $error_summary = new \Behat\Mink\Exception\ExpectationException($message, $this->getSession()->getDriver());
    throw $error_summary;
  }

  /**
   * Download a file.
   *
   * The Selenium or Webdriver sessions do not allow us access to the
   * HTTP Response code if we just 'get' a page. Also, getting a PDF
   * Stalls the browser interaction and we get stuck.
   * Instead use iTryToDownload() to retrieve a file and get the headers.
   *
   * Tested with
   * phantomjs 2.1.1,
   * Selenium 2.41.0 + Chrome,
   * guzzlehttp 6.2.0,
   * Goutte 3.1.2.
   *
   * @param string $url
   *   URL to try and retrieve.
   *
   * @see BrowserContext::theDownloadResponseStatusCodeShouldBe()
   * @see https://www.jverdeyen.be/php/behat-file-downloads/
   *
   * @When /^I try to download "([^"]*)"$/
   */
  public function iTryToDownload($url) {
    // Need to make an out-of-band request to the URL in question
    // directly in order to retrieve its headers.
    // Need to sustain cookies, because authentication is important.
    $driver = $this
      ->getSession()
      ->getDriver();
    if (!method_exists($driver, 'getWebDriverSession')) {
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

    $client = new Client([
      'base_uri' => $this->getSession()->getCurrentUrl(),
      'cookies' => $jar,
    ]);

    // It's OK if Guzzle throws errors. That's what we are expecting.
    try {
      $response = $client->get($url);
      $this->headers = $response->getHeaders();
      $this->statusCode = $response->getStatusCode();
    }
    catch (ClientException $e) {
      print $e->getMessage();
    }

    // Would be good if we could get the $driver->setStatusCode()
    // so as to let other checks function normally, but that's off-limits.
  }

  /**
   * Check HTTP response code.
   *
   * @param int $statusCode
   *   HTTP response code, eg 200, 403.
   *
   * @throws \Exception
   *
   * @see https://www.jverdeyen.be/php/behat-file-downloads/
   *
   * @Then /^the download response status code should be "?(?P<code>\d+)"?$/
   */
  public function theDownloadResponseStatusCodeShouldBe($statusCode) {
    $responseStatusCode = $this->statusCode;

    if (!$responseStatusCode == intval($statusCode)) {
      throw new \Exception(sprintf("Did not see response status code %s, but %s.", $statusCode, $responseStatusCode));
    }
  }

  /**
   * Check HTTP Reponse header.
   *
   * @param string $header
   *   Header id.
   * @param string $value
   *   Search string.
   *
   * @throws \Exception
   *
   * @see https://www.jverdeyen.be/php/behat-file-downloads/
   *
   * @Then /^the download response header should contain "([^"]*)":"([^"]*)"$/
   */
  public function theDownloadResponseHeaderShouldContain($header, $value) {
    if (empty($this->headers[$header])) {
      throw new \Exception(sprintf("Header did not contain %s.", $header));
    }
    // Header values are supplied as an array.
    if (!in_array($value, $this->headers[$header])) {
      throw new \Exception(sprintf("Header %s did not contain value %s.", $header, $value));
    }
  }

  /**
   * Select a frame by its name or ID.
   *
   * @see https://github.com/Behatch/contexts/blob/master/src/Context/BrowserContext.php
   * @see https://gist.github.com/alnutile/8365567
   *
   * @When (I )switch to iframe :name
   * @When (I )switch to frame :name
   */
  public function switchToIFrame($locator) {
    // Mink switchToIFrame requires just the ID. So need to resolve selectors.
    $iframe = $this->getSession()->getPage()->find("css", $locator);
    $iframeName = $iframe->getAttribute("id");
    if (empty($iframeName)) {
      throw new \Exception(sprintf("Did not find a named iframe '%'.", $locator));
    }
    $this->getSession()->switchToIFrame($iframeName);
  }
  /**
   * Go back to main document frame.
   *
   * @When (I )switch to main frame
   */
  public function switchToMainFrame() {
    $this->getSession()->switchToIFrame();
  }

  /**
   * To emulate file upload via drag & drop, strange magic.
   *
   * @see http://thinkmoult.com/using-sahi-mink-behat-test-html5-drag-drop-file-uploads/
   *
   * @When (I )drop file :file onto (element ):locator
   */
  public function dropFileIntoElement($file, $locator) {
    $session = $this->getSession();
    $session->evaluateScript("
      myfile = new Blob([atob('iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAAAAABVicqIAAAACXBIWXMAAAsTAAALEwEAmpwYAAAA\
B3RJTUUH3gIYBAEMHCkuWQAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUH\
AAAAQElEQVRo3u3NQQ0AAAgEoNN29i9kCh9uUICa3OtIJBKJRCKRSCQSiUQikUgkEolEIpFIJBKJ\
RCKRSCQSiUTyPlnSFQER9VCp/AAAAABJRU5ErkJggg==')], {type: 'image/png'});
      myfile.name = 'myfile.png';
      myfile.lastModifiedDate = new Date();
      myfile.webkitRelativePath = '';
      fileList = Array(myfile);
      e = jQuery.Event('drop');
      e.dataTransfer = { files: fileList };
      jQuery('$locator').bind('drop',function(n){alert('Drooped on');console.log(n)});
      jQuery('$locator').trigger(e);
      // Trigger seems to not work great
      // $('$locator').get(0).dispatchEvent(e)
    ");
    $session->wait(1000);
  }
  
}
