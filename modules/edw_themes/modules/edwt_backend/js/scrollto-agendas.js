(function ($, Drupal, once) {

  'use strict';

  Drupal.behaviors.scrollTo = {
    attach: function (context, settings) {
      const accordionId = document.location.hash.substring(1);
      let regex = new RegExp("\\/node\\/\\d*\\/documents\\/((?:.*))");
      let phase = document.location.pathname.replace(regex, '$1') ?? 'pre-session';
      if (phase.includes('/document-type')) {
        phase = phase.replace('/document-type', '');
      }
      let accordion = document.getElementById('panel-' + phase + '-by-' + accordionId);
      if (!accordion) {
        accordion = document.getElementById('panel-' + phase + '-by-document-' + accordionId);
      }
      if (accordion) {
        accordion.classList.add('show');
        accordion.scrollIntoView({behavior: "smooth"});
      }
    }
  }

}(jQuery, Drupal, once));
