(function () {
  if (!("serviceWorker" in navigator)) return;
  var inMobileDir = /\/mobile\//.test(window.location.pathname);
  var swPath = inMobileDir ? "../sw.js" : "./sw.js";
  var swScope = inMobileDir ? "../" : "./";
  window.addEventListener("load", function () {
    navigator.serviceWorker.register(swPath, { scope: swScope }).catch(function (err) {
      console.warn("Service worker registration failed", err);
    });
  });
})();
