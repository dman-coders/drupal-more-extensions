# drupal-more-extensions
Extended Drupal extensions for Behat

## Features

The actions listed here may be incomplete. Run:
    behat -dl
to list the real state of available definitions.
    behat -di
to show the associated action help.

### Screenshotting actions.

    Drupal\DrupalMoreExtensions\Context\ScreenshotContext

- @Given the viewport is :arg1
- @Given the viewport is wide/medium/narrow/mobile
- @Then I take a screenshot and save as :arg1
- @Then /^take a screenshot of "([^"]*)" and save as "([^"]*)"$/

And some runtime debug additions:

- @Then /^show me a screenshot$/
- @Then /^show me the HTML page$/


### Some additional utility actions for interacting with a web page.

    Drupal\DrupalMoreExtensions\Context\BrowserContext

- @Given I wait :arg1 seconds
- I select vertical tab "#edit-options"
- @Then /^I fill in ckeditor on field "([^"]*)" with "([^"]*)"$/
- @Then /^I fill in tinymce on field "([^"]*)" with "([^"]*)"$/

### Some additional utility actions for logging in to Drupal

    Drupal\DrupalMoreExtensions\Context\DrupalLoginContext

- @Given I log in to Drupal as :arg1 with password :arg2
- @Given I log in to OpenID as :arg1 with password :arg2
- @Given I remember cookies
- @Given I am logged in as user :name
- @Given I am authenticated with Drupal as (user) :arg1 with password :arg2
- @Given I reset the admin password to :arg1
- @Given I am logged in as the superuser

## Installation

To include these new steps and actions,

In behat.yml, include:

  default:
    suites:
      default:
        contexts:
          - Drupal\DrupalMoreExtensions\Context\DrupalLoginContext
