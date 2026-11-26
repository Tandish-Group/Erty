// Page loader: hides the .loader-wrap overlay once the page has loaded. Pages with a map wait for the
// map's "mapReady" event (or a timeout), so the map is not shown half-drawn.
(function ($) {
    "use strict";

    var handled = false;
    var expectingMap = false;

    function hideLoader() {
        if (handled) return;
        handled = true;
        if (!$('.loader-wrap').length) return;

        $('.loader-wrap .overlay').animate({ left: '100%' }, 1200, function () {
            $('.loader-wrap').fadeOut(1000, function () {
                document.dispatchEvent(new CustomEvent('loaderComplete'));
            });
        });
    }

    $(document).ready(function () {
        $('.preloader').css('opacity', '1');
        expectingMap = $('.map-container').length > 0 || $('#map').length > 0;

        if (expectingMap) {
            var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            setTimeout(hideLoader, isMobile ? 10000 : 5000);
        } else {
            setTimeout(hideLoader, 1500);
        }
    });

    $(window).on('load', function () {
        if (!expectingMap || window.mapFullyReady === true) hideLoader();
    });

    document.addEventListener('mapReady', hideLoader);

    // Never leave anyone on the loading screen.
    setTimeout(hideLoader, 4000);
})(window.jQuery);
