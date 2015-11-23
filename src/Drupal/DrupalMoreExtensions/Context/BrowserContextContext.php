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
    $this->getSession()->wait($seconds * 1000);
  }


}
