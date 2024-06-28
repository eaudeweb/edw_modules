(function ($, Drupal, once) {

    'use strict';

    Drupal.behaviors.general = {
        attach: function (context, settings) {
            $(".button--closeall").click(function () {
                $(".collapse").removeClass("show");
            });
            $(".button--openall").click(function () {
                $(".collapse").addClass("show");
            });
        }
    }

}(jQuery, Drupal, once));
