<?php

namespace Drupal\DrupalMoreExtensions\Context;

use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Drupal-specific markup.
 *
 * Suppliments Drupal\DrupalExtension\Context\MarkupContext
 * MAY contain some theme assumptions.
 *
 * Extensions to the Mink Extension.
 *
 * @see Drupal\DrupalExtension\Context\MarkupContext
 */
class MarkupContext extends RawMinkContext {

  /**
   * Return a block from the current page.
   *
   * Search for an element classed 'block'
   * that contains a 'block-title'
   * matching the search text.
   *
   * A block title is user-managed, so may be unreliable.
   * However the block ID or classes are terrible.
   * If you find the need to use a class selector, then just use
   * assertElementContainsText() etc.
   *
   * @throws \Exception
   *   If block cannot be found.
   *
   * @param string $region
   *   The machine name or title of the block to return.
   *
   * @return \Behat\Mink\Element\NodeElement
   *
   */
  public function getBlock($block) {
    $block_title_indicator = '.block-title';
    $block_element_indicator = '.block';
    $session = $this->getSession();
    // We search for the block-title first, then scan up to find its
    // nearest parent. This avoids us doing the iteration, and helps
    // ignore nesting problems.
    // TODO: replace with an xpath text().contains() lookup here?
    $allBlockTitles = $session->getPage()->findAll('css', '.block-title');
    /** @var \Behat\Mink\Element\NodeElement $found */
    $found = NULL;
    // Prior art all uses regexp instead of stristr for string searches.
    $regex = '/'.preg_quote($block, '/').'/ui';
    foreach($allBlockTitles as $title_element) {
      if (preg_match($regex, $title_element->getText())) {
        $found = $title_element;
        break;
      }
    }
    if (!$found) {
      throw new \Exception(sprintf('No block title "%s" found on the page %s.', $block, $session->getCurrentUrl()));
    }

    // Expand up from the found title to the block element.
    // Either crawl the DOM manually or use xpath-fu.
    // Beware false-positives when string-matching class attributes :(.
    // Find DOM ancestors that are called 'block's:
    $xpath = 'ancestor::*[contains(@class, "block")][last()]';
    // I EXPECT that $found should be getting used as the context here.
    $wrappers = $found->findAll('xpath', $xpath);
    // WOW, magic happened. The current selector context is resolved and
    // prepended, producing like:
    // ((//html/descendant-or-self::*[@class and contains(concat(' ', normalize-space(@class), ' '), ' block-title ')])[3]/ancestor::*[contains(@class, "block")])
    // We need the shortest path instead to get nearest-parent.
    // That list seems to return outermost wrapper first,
    // so using [last[]] pops that.
    /*
    foreach($wrappers as $wrapper) {
      print "\n\n***\n";
      print_r(substr($wrapper->getOuterHtml(), 0, 1000));
    }
    */
    /** @var \Behat\Mink\Element\NodeElement $wrapper */
    $wrapper = array_pop($wrappers);
    if (!$wrapper) {
      throw new \Exception(sprintf('No block element wrapping the title "%s" found on the page %s. This is probably due to css un-theming.', $block, $session->getCurrentUrl()));
    }
    return $wrapper;
  }

  /**
   * @Then I( should) see a/the :block block
   */
  public function assertBlock($block) {
    $blockObj = $this->getBlock($block);
    if (!empty($blockObj)) {
      return;
    }
    throw new \Exception(sprintf('The "%s" block was not found on the page %s', $block, $this->getSession()->getCurrentUrl()));
  }

  /**
   * @Then I( should) see the :tag element in the :block( block)
   */
  public function assertBlockElement($tag, $block) {
    $blockObj = $this->getBlock($block);
    $elements = $blockObj->findAll('css', $tag);
    if (!empty($elements)) {
      return;
    }
    throw new \Exception(sprintf('The element "%s" was not found in the "%s" block on the page %s', $tag, $block, $this->getSession()->getCurrentUrl()));
  }
  /**
   * @Then I( should) see :text( text) in the :block( block)
   */
  public function assertBlockText($text, $block) {
    $blockObj = $this->getBlock($block);
    $regex = '/'.preg_quote($text, '/').'/ui';
    if (preg_match($regex, $blockObj->getText())) {
      return;
    }
    throw new \Exception(sprintf('The text "%s" was not found in the "%s" block on the page %s', $text, $block, $this->getSession()->getCurrentUrl()));
  }

}
