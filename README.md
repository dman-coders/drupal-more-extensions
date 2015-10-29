# drupal-more-extensions
Extended Drupal extensions for Behat


To include these new steps and actions,

In behat.yml, include:

  default:
    suites:
      default:
        contexts:
          - Drupal\DrupalMoreExtensions\Context\DrupalLoginContext
