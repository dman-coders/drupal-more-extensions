@smoketest
@javascript
@screenshots

Feature: Check Ability to screenshot
  In order to ensure the testing connections are working
  As a tester
  I need to get a screenshot of the browser.

  Scenario: Test screenshooting the front page
    Given I am on the homepage
    Then show me a screenshot

  Scenario: Test screenshotting a page element.
    Given I am on the homepage
    Then take a screenshot of "h1" and save "home-h1"
