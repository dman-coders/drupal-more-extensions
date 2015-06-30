<?php

namespace Drupal\DrupalExtension\Context;

use Behat\Behat\Context\TranslatableContext;
use Behat\Mink\Element\Element;


/**
 * Provides additional Drupal user account and authentication actions.
 */
class DrupalLoginContext extends RawDrupalContext {


  /**
   * Just a check to assert that this library is being included correctly.
   *
   * @Given I have DrupalLoginContext available
   */
  public function assertLoginContextExists() {
    // Verify the user is logged out.
    if ($this->loggedIn()) {
      $this->logout();
    }
  }

  /**
   * @Given I wait :arg1 seconds
   */
  public function iWaitSeconds($seconds) {
    $this->getSession()->wait($seconds * 1000);
  }

  /**
   * @Given I log in to Drupal as :arg1 with password :arg2
   *
   * This is a declaritive action, not just a precondition.
   * This will always clear any existing session and go through the login
   * screens. Use "I am Authenticated" for a smoother ride.
   */
  public function iLogInToDrupalAsWithPassword($username, $password) {
    // Subroutine to pack things simpler
    $this->getSession()->visit($this->locatePath('/logout'));
    $this->getSession()->visit($this->locatePath('/user/login'));
    $element = $this->getSession()->getPage();
    $element->fillField('name', $username);
    $element->fillField('pass', $password);
    $submit = $element->findButton('Log in');
    $submit->click();
  }

  /**
   * @Given I log in to OpenID as :arg1 with password :arg2
   */
  public function iLogInToOpenidAsWithPassword($username, $password) {
    // Subroutine to pack things simpler
    // The session is actually destroyed for each case, so yeah,
    // I do have to log in each time.
    $this->getSession()->visit($this->locatePath('/logout'));
    $this->getSession()->visit($this->locatePath('/openid/login'));
    $element = $this->getSession()->getPage();
    $element->fillField('name', $username);
    $element->fillField('pass', $password);
    $submit = $element->findButton('Log in');
    $submit->click();
  }

  /**
   * @Given I remember cookies
   *
   * This will save any current browser cookies into test-session memory
   * and retrieve any cookies out of the session memory if missing, restoring
   * them to the browser.
   *
   * Making this assertion at any step will enable you to restore browser state
   * in a later scenario.
   * It's best not to do this directly, as this breaks the paradigm of tests
   * being entirely independent, but it can be used to logically keep you
   * logged in or at a certain state of interaction.   *
   */
  public function iRememberCookies() {
    $session = $this->getSession();
    $driver = $session->getDriver();
    $wdSession = $driver->getWebDriverSession();

    // Restore any cookies that were set.
    $remembered_cookies = isset($session->remember_cookies) ? $session->remember_cookies : array();
    #print "\nRemembered Cookies\n";
    #print_r($remembered_cookies);
    $browser_cookies = $wdSession->getAllCookies();
    #print "\nBrowser Cookies\n";
    #print_r($browser_cookies);

    // Restore them from memory into the browser if missing.
    foreach ($remembered_cookies as $cookie_info) {
      if (!$session->getCookie($cookie_info['name'])) {
        $cookie_data = urldecode($cookie_info['value']);
        $session->setCookie($cookie_info['name'], urldecode($cookie_info['value']));
      }
    }

    // Abuse the object by adding data directly to it.
    $browser_cookies = $wdSession->getAllCookies();
    $session->remember_cookies = $browser_cookies;
  }

  /**
   * Utility for checking text.
   * There is probably an available lib for this elsewhere, but it's hard to find.
   */
  private function _responseContains($text) {
    $actual = $this->getSession()->getPage()->getContent();
    $regex  = '/'.preg_quote($text, '/').'/ui';
    $message = sprintf('The string "%s" was not found anywhere in the HTML response of the current page.', $text);

    return(bool) preg_match($regex, $actual);
  }


  /**
   * Passive login check.
   *
   * @Given I am authenticated with Drupal as (user) :arg1 with password :arg2
   * @Given I am logged in as (user) :arg1 with password :arg2
   *
   * -   * Similar to iLogIn, but checks to see if it's really neccessary
   * -   * to do the login. Does it if needed, but re-uses the session if not.
   * -   * This is a softer precondition, as it just checks you are logged in
   * -   * and only goes through the login screens if not.
   * -   *
   * -   * Inspired by a solution found at
   * -   *  http://robinvdvleuten.nl/blog/handle-authenticated-users-in-behat-mink/
   *   *
   */
  public function iAmAuthenticatedWithDrupalAsWithPassword($username, $password) {
    $session = $this->getSession();
    $driver = $session->getDriver();
    // If you see this on the screen, you are logged in.
    $text_showing_you_are_logged_in = "Log out $username";
    if ($this->_responseContains($text_showing_you_are_logged_in)) {
      // The page currently showing implies we are logged in,
      // so if the cookies are good, then we are good to go.
      // All I have to do is recover the session persistence.
      $this->iRememberCookies();
    }
    else {
      // This method returns a list of further steps, it
      // doesn't evaluate them immediately.
      return $this->iLogInToDrupalAsWithPassword($username, $password);
    }
  }

  /**
   * @Given that I am authenticated as :arg1 with password :arg2
   */
  public function thatIAmAuthenticatedAsWithPassword($arg1, $arg2) {
    throw new PendingException();
  }

}
