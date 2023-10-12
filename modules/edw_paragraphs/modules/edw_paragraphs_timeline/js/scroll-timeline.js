(function ($, Drupal) {

    Drupal.behaviors.scoll_timeline = {
      attach: function (context, settings) {
        const scrollIcon = $('#scroll-icon');
        const timeline = $('.timeline');
        if (timeline == null) {
          return;
        }
        if (scrollIcon !== null) {
          scrollIcon.on('click', function () {
            $([document.documentElement, document.body]).animate({
              scrollTop: scrollIcon.offset().top
            }, 1);
          });
        }
        window.addEventListener("scroll", revealTimeline);
        revealTimeline();

        function revealTimeline() {
          const windowScrollTop = $(window).scrollTop();
          const timelineTop = timeline.offset().top;
          const timelineBottom = timeline.outerHeight() + timeline.offset().top;
          const revealHeight = windowScrollTop + 3 * window.innerHeight / 4;
          const currentHeight = Math.min(revealHeight, timelineBottom - 40);
          document.documentElement.style.setProperty('--h', (currentHeight - timelineTop + 40) + `px`);
          const contentElements = document.querySelectorAll('.content');
          const pseudoElements = document.querySelectorAll('.float-end, .right, .timeline-year, .timeline-item, .featured');
          fadeInElements(contentElements, "active", currentHeight);
          fadeInElements(pseudoElements,"active-marker", currentHeight);
        }

        function fadeInElements(elements, className, currentHeight) {
          for (let i = 0; i < elements.length; i++) {
            const element = $(elements[i]);
            if (element.offset().top < currentHeight) {
              element.addClass(className);
            }
            else {
              element.removeClass(className);
            }
          }
        }
      }
    }
  }
)(jQuery, Drupal);
