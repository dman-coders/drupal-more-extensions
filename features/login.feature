@smoketest
@d7

Feature: Basic Authentication tests
  Trial logging in through the Drupal Web UI
  

  Scenario: Can log in using a pre-existing tester account, given password
    Given I am an anonymous user
    And I visit '/user'
    And show me the HTML page

    Then I should see the heading "Login"
    Given I am logged in as user "tester" with password "tester"
    And I visit '/user'
    And show me the HTML page
    # Check Drupal markup to see if the login was successful.
    Then I should see a ".page-user" element
    Then I should see an ".profile" element

  Scenario: Can log in using a pre-configured tester account, using credentials from config
    Given I am an anonymous user
    And I visit '/user'
    Then I should see the heading "Login"
    Given I am logged in as user "member"
    And show me the HTML page
    Then I should see a ".page-user" element
    Then I should see an ".profile" element

