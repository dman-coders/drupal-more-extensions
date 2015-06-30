<?php

namespace Drupal\DrupalExtension\Context;

use Behat\Behat\Context\TranslatableContext;
use Behat\Mink\Element\Element;


/**
 * Provides additional Drupal user account and authentication actions.
 */
class DrupalLoginContext extends RawDrupalContext implements TranslatableContext {


  /**
   * @Given I am an anonymous user
   * @Given I am not logged in
   */
  public function assertAnonymousUser() {
    // Verify the user is logged out.
    if ($this->loggedIn()) {
      $this->logout();
    }
  }


}
