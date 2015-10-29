<?php

namespace Drupal\DrupalExtension\Context;


/**
 * Provides additional Drupal user account and authentication actions.
 */
class DrupalLoginContext extends RawDrupalContext {


  /**
   * Keep track of available users - provided by the behat.local.yml
   *
   * @var array
   */
  protected $users = array();

  /**
   * Just a check to assert that this library is being included correctly.
   * Used for internal testing of the features only.
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
    $this->logout();
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
    // The session is actually destroyed for each case, so yeah,
    // I do have to log in each time.
    $this->logout();
    $this->getSession()->visit($this->locatePath('/openid/login'));
    $element = $this->getSession()->getPage();
    $element->fillField('name', $username);
    $element->fillField('pass', $password);
    $submit = $element->findButton('Log in');
    $submit->click();
  }

  /**
   * BROKEN
   *
   * @Given I remember cookies
   *
   * Broken in Behat 3 - used to work in Behat 1. with Seleniums WDriver
   *
   * This will save any current browser cookies into test-session memory
   * and retrieve any cookies out of the session memory if missing, restoring
   * them to the browser.
   *
   * Making this assertion at any step will enable you to restore browser state
   * in a later scenario.
   * It's best not to do this directly, as this breaks the paradigm of tests
   * being entirely independent, but it can be used to logically keep you
   * logged in or at a certain state of interaction.
   *
   * We are not permitted via the normal API to collect a cookie we don't
   * know the name of, so can't retrieve SESSed89abb4957e9e8f5889af09647eaf15
   *
   * Thus, we need to dig into the sessions web driver a bit deeper.
   * Problem is, the CoreDriver has made its $client private.
   */
  public function iRememberCookies() {
    $session = $this->getSession();
    $driver = $session->getDriver();

    // If there was a session running, check its headers for cookies.
    try {
      if (!empty($session->getCurrentUrl())) {
        $response_headers = $driver->getResponseHeaders();
        // print_r($response_headers);
      }
    } catch (\Behat\Mink\Exception\DriverException $e) {
      //  Unable to access the request before visiting a page.
      // Ignore it then.
    }

    // Restore any cookies that were set.
    $remembered_cookies = isset($session->remember_cookies) ? $session->remember_cookies : array();
    #print "\nRemembered Cookies\n";
    #print_r($remembered_cookies);
    # $browser_cookies = $wdSession->getAllCookies();
    #print "\nBrowser Cookies\n";
    #print_r($browser_cookies);

    // Restore them from memory INTO the browser if missing.
    foreach ($remembered_cookies as $cookie_info) {
      if (!$session->getCookie($cookie_info['name'])) {
        # $cookie_data = urldecode($cookie_info['value']);
        $session->setCookie($cookie_info['name'], urldecode($cookie_info['value']));
      }
    }

    // Abuse the object by adding data directly to it.

    #$allValues = $driver->getCookieJar()->allValues($this->getCurrentUrl());
    #$browser_cookies = $wdSession->getAllCookies();
    #$session->remember_cookies = $browser_cookies;
  }

  /**
   * Utility for checking text.
   * There is probably an available lib for this elsewhere, but it's hard to find.
   */
  private function _responseContains($text) {
    $actual = $this->getSession()->getPage()->getContent();
    $regex  = '/'.preg_quote($text, '/').'/ui';
    return(bool) preg_match($regex, $actual);
  }


  /**
   * @Given I am logged in as :name
   *
   * Requires that this extension was configured to know the usernames and
   * passwords already, probably passed in via the behat.local.yml config.
   */
  public function assertLoggedInByName($name) {
    if (!isset($this->users[$name])) {
      throw new \Exception(sprintf('No user with %s name is registered with the DrupalLoginContext driver.', $name));
    }

    // Change internal current user.
    // $this->user = $this->users[$name];

  }



/**
   * Passive login check.
   *
   * @Given I am authenticated with Drupal as (user) :arg1 with password :arg2
   * @Given I am logged in as (user) :arg1 with password :arg2
   *
   * Similar to iLogIn, but checks to see if it's really neccessary
   * to do the login. Does it if needed, but re-uses the session if not.
   * This is a softer precondition, as it just checks you are logged in
   * and only goes through the login screens if not.
   *
   * Inspired by a solution found at
   * http://robinvdvleuten.nl/blog/handle-authenticated-users-in-behat-mink/
   *
   */
  public function iAmAuthenticatedWithDrupalAsWithPassword($username, $password) {
    $session = $this->getSession();
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
   * Dangerous, and currently requires drush only.
   *
   * @Given I reset the admin password to :arg1
   */
  public function iResetTheAdminPasswordTo($password)
  {
    // HOW TO ENSURE driver is drush?
    if (TRUE || $driver_is_drush ) {
      // Before I can reset uid1 pass, I need to know its name;
      $drush_response = $this->getDriver()->drush('user-information --fields=name --format=json 1');
      $info = json_decode($drush_response);
      if (!empty($info)) {
        $user = reset($info);
      }
      $this->getDriver()->drush("user-password '" . $user->name . "' --password='${password}'");
    }
  }

  /**
   * Use a drush user-login password reset to grab the admin account.
   *
   * @Given I am logged in as the superuser
   */
  public function iAmLoggedInAsTheSuperuser() {
    // First check if I'm already logged in, as it's slow.
    $this->getSession()->visit($this->locatePath('/user/1/edit'));
    if ($this->getSession()->getStatusCode() == 200) {
      print("Already super!");
      return TRUE;
    }
    // If I was another account, logout.
    $this->logout();

    // Need to run this (login session) from blackbox.
    // BUT need to run from API first to get the backdoor to reset admin.
    // If I run as API I get blackbox also.

    // Can I tell if I'm running in API/drush context?
    #print_r(array_keys((array)$this));

    $drush_response = trim($this->getDriver()->drush('user-login --browser=0 1'));
    // I now have a login reset link for UID1
    $url_parts = parse_url($drush_response);
    // If drush and our session disagree about what the base URL is,
    // due to ports or DNS, that's sad. So just re-resolve the path.
    $this->getSession()->visit($this->locatePath($url_parts['path']));
    $actual = $this->getSession()->getPage()->getContent();
    // I should see superadmin username etc.
    // TODO a test here to check the text on the reset page.
    # print_r($actual);
  }

  /**
   * UNTESTED
   *
   * Credits https://github.com/previousnext/agov/blob/7.x-3.x/tests/behat/bootstrap/FeatureContext.php
   * for drupalextension 1.0 version
   *
   * @Given /^an "([^"]*)" user named "([^"]*)"$/
   */
  public function anUserNamed($role_name, $username) {
    // Create user (and project)
    $user = (object) array(
      'name' => $username,
      'pass' => $this->getDriver()->getRandom()->name(16),
      'role' => $role_name,
    );
    $user->mail = "{$user->name}@example.com";
    // Create a new user.
    $this->getDriver()->userCreate($user);
    $this->users[$user->name] = $user;
    $this->getDriver()->userAddRole($user, $role_name);
  }

  /**
   * UNTESTED - assumes core driver
   *
   * Creates and authenticates a user with the given role via Drush.
   *
   * Overrides DrupalContext::assertAuthenticatedByRole() to make sure pathauto
   * doesn't hose the menu_router table.
   *
   * Credits https://github.com/previousnext/agov/blob/7.x-3.x/tests/behat/bootstrap/FeatureContext.php
   * for drupalextension 1.0 version
   *
   * @override Given /^I am logged in as a user with the "(?P<role>[^"]*)" role$/
   */
  public function assertAuthenticatedByRole($role) {
    // Check if a user with this role is already logged in.
    if ($this->loggedIn() && $this->user && isset($this->user->role) && $this->user->role == $role) {
      return TRUE;
    }
    // Create user (and project)
    $user = (object) array(
      'name' => $this->getDriver()->getRandom()->name(8),
      'pass' => $this->getDriver()->getRandom()->name(16),
      'role' => $role,
    );
    $user->mail = "{$user->name}@example.com";
    // Create a new user.
    $this->getDriver()->userCreate($user);
    $this->users[$user->name] = $this->user = $user;
    if ($role == 'authenticated user') {
      // Nothing to do.
    }
    else {
      $this->getDriver()->userAddRole($user, $role);
    }
    // This is to remove password policy issues.
    db_update('password_policy_force_change')
      ->fields(array(
        'force_change' => 0,
      ))
      ->condition('uid', $user->uid)
      ->execute();
    db_delete('password_policy_expiration')
      ->condition('uid', $user->uid)
      ->execute();
    // Login.
    $this->login();
    return TRUE;
  }

  /**
   * INCOMPLETE
   *
   * Create the named role if it does not exist.
   *
   * @Given /^a role named "([^"]*)"$/
   */
  public function anRoleNamed($role_name) {
    // Create a new user.
    $this->getDriver()->roleCreate($role_name);
  }

}
