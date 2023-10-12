(function ($, Drupal, drupalSettings, once) {
  function dynamicMode(context, element, paragraph, $lgContainer, optionset) {
    // See more: https://www.lightgalleryjs.com/demos/dynamic-mode.
    optionset = {
      autoplayFirstVideo: false,
      pager: false,
      galleryId: "nature",
      plugins: [lgZoom, lgThumbnail],
      mobileSettings: {
        controls: false,
        showCloseIcon: false,
        download: false,
        rotate: false
      }
    };
    $(paragraph, context).justifiedGallery({
      captions: false,
      rowHeight: 180,
      margins: 5
    }).on("jg.complete", function () {
      lightGallery(
        $lgContainer,
        optionset
      );
    });
  }
  function inlineMode(context, element, paragraph, $lgContainer, optionset) {
    let paragraphId = paragraph.data('paragraph-id');
    // See more: https://www.lightgalleryjs.com/demos/inline.
    optionset = {
      dynamic: true,
      hash: false,
      closable: false,
      showMaximizeIcon: true,
      slideDelay: 400,
      plugins: [lgZoom, lgThumbnail],
      container: $lgContainer,
      thumbMargin: 4,
      appendSubHtmlTo: '.lg-item',
      dynamicEl: eval(drupalSettings['dynamicEl-' + paragraphId])
    };
    $(paragraph, context).css('width', '100%');
    $(paragraph, context).css('height', '0');
    $(paragraph, context).css('padding-bottom', '65%');
    let inlineGallery = lightGallery(
      $lgContainer,
      optionset
    );
    setTimeout(() => {
      inlineGallery.openGallery();
    }, 200);
  }

  Drupal.behaviors.lightgallery = {
    galleries: [],
    attach: function (context, settings) {
      once('lightgallery', 'div.lightgallery', context).forEach(function (element) {
        let paragraph = $('#' + element.id);
        let mode = paragraph.data('mode');
        let $lgContainer = document.getElementById(element.id);
        let optionset = {};

        switch(mode) {
          case 'thumbnails':
            dynamicMode(context, element, paragraph, $lgContainer, optionset)
            break;

          case 'inline':
            inlineMode(context, element, paragraph, $lgContainer, optionset);
            break;
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
