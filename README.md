# drupal-more-extensions
Extended Drupal extensions for Behat

For setup instructions on getting testing for Drupal running,
see the drupal-extension docs
https://behat-drupal-extension.readthedocs.io/

This set of 'Contexts' provides a few more sets of actions we can test.

## Install

To include these contexts in your project:

* Include this library in your behat test project composer.json

      {
        "require": {
          /* Other stuff */
          "dman-coders/drupal-more-extensions": "~1.0@dev"
        },
        "repositories": [
          {
            "type": "vcs",
            "url": "git@github.com:jhedstrom/drupalextension.git"
          }
        ],
      }

  And run `composer update`.

* Include the DrupalMoreExtensions Context(s) in your behat.yml

      default:
        suites:
          default:
            contexts:
              - FeatureContext
              - Drupal\DrupalExtension\Context\DrupalContext
              /* Other Stuff */
              - Drupal\DrupalMoreExtensions\Context\BrowserContext
              - Drupal\DrupalMoreExtensions\Context\ScreenshotContext:
                  params:
                    path: 'screenshots'
                    timestamped: false

  And you should see the new commands become available.


## Features

The actions listed here may be incomplete. Run:
    `behat -dl`
to list the real state of available definitions.
    `behat -di`
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
