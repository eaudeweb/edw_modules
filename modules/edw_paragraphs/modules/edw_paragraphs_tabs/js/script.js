/**
 * @file
 * Description.
*/

(function (Drupal, once) {
  Drupal.behaviors.edw_paragraphs_tabs = {
    attach: function (context) {
      const elements = once('edw_paragraphs_tabs', '.js-btn-tab', context);
      elements.forEach(function (item) {
        item.addEventListener("click", () => {
          const url = new URL(window.location.href);
          const ariaValue = item.getAttribute('aria-controls');

          url.searchParams.delete('activeTab');
          url.searchParams.set('activeTab', ariaValue);
          window.history.replaceState(null, null, url); // or pushState
        });
      });
    }
  };
})(Drupal, once);
