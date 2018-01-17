<?php

namespace Drupal\DrupalMoreExtensions\Context;

use Behat\Mink\Exception\DriverException;
use Drupal\Driver\Exception\Exception;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Provides additional Drupal user account and authentication actions.
 *
 * Uses a lot of session and authentication logins and checks.
 * Somewhat trivial, but also somewhat volatile when working on black boxes
 * that may be thrown off by theming changes etc.
 */
class DrupalLoginContext extends RawDrupalContext {

  /**
   * Keep track of available users - provided by the behat.local.yml.
   *
   * This is different from the $users list which is managed by
   * RawDrupalContext and enumerates the temporary test users
   * (and will delet them at the end of the run)
   *
   * @var array
   */
  protected $userCredentials = array();

  /**
   * We expect to be given an array of user accounts.
   *
   * The yml can set them during setup as so:
   *
   * default:
   *   suites:
   *     default:
   *       contexts:
   *         - Drupal\DrupalMoreExtensions\Context\DrupalLoginContext:
   *             users:
   *               admin:
   *                 name: admin
   *                 pass: adminpass
   *
   * (BEWARE INDENTATION ISSUES!)
   *
   * @param array[] $users
   *   List of basic user definitions.
   */
  public function __construct($users = array()) {
    echo("Constructing DrupalLoginContext, initializing user credentials. \n");
    // The provided settings come in as arrays,
    // but the rest of the system (specifically $this->login()) expects
    // objects, so cast them to objects now.
    foreach ((array) $users as $key => $val) {
      $users[$key] = (object) $val;
    }
    // These are just examples, expected to be over-ridden by yml configs.
    $users += array(
      'admin' => (object) array('name' => 'dummy', 'pass' => 'dummy'),
      'editor' => (object) array('name' => 'dummy', 'pass' => 'dummy'),
      'member' => (object) array('name' => 'dummy', 'pass' => 'dummy'),
    );
    $this->userCredentials = $users;
  }

  /**
   * Check to assert that this library is being included correctly.
   *
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
   * Drupal-specific front-end login.
   *
   * This is a declaritive action, not just a precondition.
   * This will always clear any existing session and go through the login
   * screens. Use "I am Authenticated" for a smoother ride.
   *
   * @param string $username
   *   Existing user.
   * @param string $password
   *   Existing password.
   *
   * @throws \Exception
   *
   * @Given I log in to Drupal as (user ):arg1 with password :arg2
   */
  public function iLogInToDrupalAsWithPassword($username, $password) {
    $this->logout();
    $this->getSession()->visit($this->locatePath('/user/login'));
    $element = $this->getSession()->getPage();
    $element->fillField('name', $username);
    $element->fillField('pass', $password);
    $submit = $element->findButton('Log in');
    $submit->click();
    // Verify that this worked.
    # $actual = $this->getSession()->getPage()->getContent();

    // Find the message. Drupal MessageContext can't be called from here?
    $message_selector = $this->getDrupalSelector('message_selector');
    $selectorObjects = $this->getSession()->getPage()->findAll("css", $message_selector);
    $messages = "";
    foreach ($selectorObjects as $selectorObject) {
      $messages .= $selectorObject->getText();
    }

    if (!$this->loggedIn()) {
      // print_r($actual);
      print_r($messages);
      throw new \Exception(sprintf("Failed to log in as user '%s'. '%s'", $username, $messages));
    }
    // If success, update the user object as best I can.
    $this->checkWhoIAm();
  }

  /**
   * Perform login using given credentials.
   *
   * This is a declaritive action, not just a precondition.
   * This will always clear any existing session and go through the login
   * screens.
   *
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
   * Checks user is authenticated.
   *
   * Requires that this extension was configured to know the usernames and
   * passwords already, probably passed in via the behat.local.yml config.
   *
   * @param string|int $name
   *   User ID or user name.
   *
   * @return bool
   *   Success.
   *
   * @throws \Exception
   *   Named user is not defined in the yml configs.
   *
   * @see __construct()
   *
   * @Given I am logged in (to Drupal )as (user ):name
   * @Given I am authenticated (to Drupal )as (user ):name
   */
  public function iAmLoggedInAsUser($name) {
    echo("Ensuring I am logged in as $name");
    // $this->printDebug(print_r($this->getMainContext(), 1));.

    $this->checkWhoIAm();
    if (!empty($this->user) && $this->user->name == $name) {
      // Already that user.
      return TRUE;
    }

    if (!isset($this->userCredentials[$name])) {
      throw new \Exception(sprintf('No user with name "%s" is registered with the DrupalLoginContext driver. Named user accounts test credentials must be defined in the projects yaml file.', $name));
    }

    // Change internal current user.
    // From the internal pre-configured list of users.
    $this->user = $this->userCredentials[$name];
    $this->login();
  }

  public function getPageTitle() {
    $h1Element = $this->getSession()->getPage()->find('css', 'h1');
    if (empty($h1Element)) {
      throw new \Exception('No H1 found on the page ' . $this->getSession()->getCurrentUrl());
    }
    return $h1Element->getText();
  }

  /**
   * Update the user object as best I can.
   *
   * Do that by visiting my own account page and scraping it.
   * Updates this->user object.
   */
  private function checkWhoIAm() {
    if (!$this->loggedIn()) {
      unset($this->user);
      return NULL;
    }
    $this->getSession()->visit($this->locatePath('/user'));
    $username = $this->getPageTitle();

    // Deducing the UID takes deeper scraping.
    // Locate the 'View' account tab link.
    $link = $this->getSession()->getPage()->find('css', '.tabs .active a');
    $link->click();
    // Exaine the current url (/user/123) to find the UID.
    $url_parts = parse_url($this->getSession()->getCurrentUrl());
    $path_parts = explode('/', $url_parts['path']);
    $uid = $path_parts[2];
    $this->user = (object) array(
      'name' => $username,
      'uid' => $uid,
    );
    # print_r($this->user);
    return $this->user;
  }

  /**
   * Checks that the current session is authenticated as a user ID.
   *
   * @param int $uid
   *   User ID.
   *
   * @return bool
   *   Success
   *
   * @throws \Exception
   *   Failure.
   */
  public function assertIamUserId($uid) {
    if ($this->user->uid == $uid) {
      return TRUE;
    }
    throw new \Exception("Not the expected User ID. Expected $uid, got " . $this->user->uid);
  }

  /**
   * Passive login check. Logs in if needed.
   *
   * Similar to iLogIn, but checks to see if it's really neccessary
   * to do the login. Does it if needed, but re-uses the session if not.
   * This is a softer precondition, as it just checks you are logged in
   * and only goes through the login screens if not.
   *
   * Inspired by a solution found at
   * http://robinvdvleuten.nl/blog/handle-authenticated-users-in-behat-mink/
   *
   * @Given I am authenticated with Drupal as (user) :arg1 with password :arg2
   * @Given I am logged in as (user) :arg1 with password :arg2
   */
  public function iAmAuthenticatedWithDrupalAsWithPassword($username, $password) {
    if ($this->loggedIn()) {
      $this->checkWhoIAm();
      if ($this->user->name == $username) {
        return TRUE;
      }
      else {
        // Logged in, but not as correct user.
        $this->logout();
      }
    }
    // Need to log in.
    return $this->iLogInToDrupalAsWithPassword($username, $password);
  }

  /**
   * Dangerous, and currently requires drush only.
   *
   * @Given I reset the admin password to :arg1
   */
  public function iResetTheAdminPasswordTo($password) {
    // HOW TO ENSURE driver is drush?
    if (TRUE || $driver_is_drush) {
      // Before I can reset uid1 pass, I need to know its name;.
      $drush_response = $this->getDriver()->drush('user-information --fields=name --format=json 1');
      $info = json_decode($drush_response);
      if (!empty($info)) {
        $user = reset($info);
      }
      $this->getDriver()->drush("user-password '" . $user->name . "' --password='${password}'");
    }
  }

  /**
   * Log in as UID1.
   *
   * Uses a backend user-login reset
   * to grab the admin account.
   *
   * @Given I am logged in as the superuser
   */
  public function iAmLoggedInAsTheSuperuser() {
    $this->iAmLoggedbyResettingThePasswordFor(1);
    // And extra-check it went as expected.
    $this->assertIamUserId(1);
  }

  /**
   * Log in using user-password reset.
   *
   * @Given I am logged by resetting the password for (user ):user
   */
  public function iAmLoggedbyResettingThePasswordFor($name) {
    // First check if I'm already logged in, as it's slow.
    $this->checkWhoIAm();
    if (!empty($this->user) && ($this->user->uid == $name ||  $this->user->name == $name)) {
      return TRUE;
    }

    // If I was another account, logout.
    $this->logout();

    // Need to run this (login session) from blackbox.
    // BUT need to run from API first to get the backdoor to reset admin.
    // If I run as API I get blackbox also.
    // Can I tell if I'm running in API/drush context?
    print("Generating password reset/back-end login\n");

    // To log in as a user without using the 'login()' I need to
    // generate a user-login via drush.
    // TODO - figure what circumstances this works in and which doesn't.
    // The drush command accepts either uid or username.
    $drush_response = trim($this->getDriver()->drush('user-login --browser=0 $name'));
    // I now have a login reset link.
    //
    // 2016-12
    // For unknown reasons, sometimes drush 8.0.0 was returning TWO URLs.
    print("Drush said $drush_response\n");
    $urls = preg_split('/\s+/', $drush_response);
    $url = end($urls);

    $url_parts = parse_url($url);
    // If drush and our session disagree about what the base URL is,
    // due to ports or DNS, that's sad. So just re-resolve the path.
    $this->getSession()->visit($this->locatePath($url_parts['path']));
    // Expect to see the "One time only login" here.
    $this->getSession()->getPage()->pressButton("Log in");

    // That visit should have logged me in.
    // Beware - Side effect of checking loggedIn() MAY change the current page!
    if ($this->loggedIn()) {
      print "Logged in as $name";
      // Update the user object.
      $this->checkWhoIAm();
      return TRUE;
    }
    else {
      throw new \Exception("Login as user '$name' failed.");
    }
  }

  /**
   * UNTESTED.
   *
   * Credits https://github.com/previousnext/agov/blob/7.x-3.x/tests/behat/bootstrap/FeatureContext.php
   * for drupalextension 1.0 version.
   *
   * @Given a :role_name user named :username
   */
  public function aRoleUserNamed($role_name, $username) {
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
   * UNTESTED - assumes core driver.
   *
   * Creates and authenticates a user with the given role via Drush.
   *
   * Overrides DrupalContext::assertAuthenticatedByRole() to make sure pathauto
   * doesn't hose the menu_router table.
   *
   * Credits https://github.com/previousnext/agov/blob/7.x-3.x/tests/behat/bootstrap/FeatureContext.php
   * for drupalextension 1.0 version
   *
   * @see DrupalContext::assertAuthenticatedByRole()
   *
   * @override XGiven /^I am logged in as a user with the "(?P<role>[^"]*)" role$/
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
   * INCOMPLETE.
   *
   * Create the named role if it does not exist.
   *
   * @Given a role named :role_name
   */
  public function CreateRoleNamed($role_name) {
    // Create a new role.
    $this->getDriver()->roleCreate($role_name);
  }

  /**
   * Deletes a user account.
   *
   * If a user with given email address exists, delete it.
   * Uses the API driver.
   *
   * @Given the account with email :mail has been deleted
   */
  public function UserHasBeenDeleted ($mail) {
    $driver = $this->getDriver();
    $user = $driver->userInformation ($mail);
    if ($user) {
      $driver->userDelete ($user);
    }
  }

}
