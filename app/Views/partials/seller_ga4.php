<?php
$ga4Id=\App\Services\SellerGa4Service::forDesigner(
    (int)($ga4DesignerId??0)
);

if ($ga4Id !== ''):
?>
<div id="cm-ga4-privacy"
     style="display:none;position:fixed;bottom:12px;left:12px;
     right:12px;max-width:440px;margin:auto;padding:16px;
     border-radius:12px;background:#382047;color:#fff;
     z-index:9999;box-shadow:0 8px 30px #0005;
     font:14px/1.5 Arial,sans-serif">
  <strong>Analytics &amp; Privacy</strong>
  <p style="margin:8px 0 12px">
    This seller uses Google Analytics to understand visits
    to their shop. May we enable optional analytics cookies?
  </p>
  <button type="button" id="cm-ga4-accept"
    style="padding:10px 14px;border:0;border-radius:7px;
    background:#ffabd9;color:#382047;font-weight:bold">
    Allow analytics
  </button>
  <button type="button" id="cm-ga4-decline"
    style="padding:10px 14px;border:1px solid #fff;
    border-radius:7px;background:transparent;color:#fff">
    No thanks
  </button>
</div>
<button type="button" id="cm-ga4-settings"
  style="position:fixed;bottom:7px;right:8px;z-index:9998;
  display:none;border:1px solid #ddd;border-radius:6px;
  background:#fff;color:#382047;padding:5px 9px;
  font-size:11px">
  Analytics privacy
</button>
<script>
(function () {
  'use strict';

  const measurementId = <?=json_encode($ga4Id)?>;
  const sellerId = <?=(int)$ga4DesignerId?>;
  const key = 'cm_ga4_consent_v2_' + sellerId + '_' + measurementId;
  const banner = document.getElementById('cm-ga4-privacy');
  const settings = document.getElementById('cm-ga4-settings');
  const accept = document.getElementById('cm-ga4-accept');
  const decline = document.getElementById('cm-ga4-decline');

  let choice = null;
  let loaded = false;

  try {
    choice = localStorage.getItem(key);
  } catch (e) {}

  function save(value) {
    choice = value;
    try {
      localStorage.setItem(key,value);
    } catch (e) {}
  }

  function loadAnalytics() {
    if (loaded) return;
    loaded = true;

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () {
      window.dataLayer.push(arguments);
    };

    window.gtag('js',new Date());
    window.gtag('consent','default',{
      analytics_storage:'granted',
      ad_storage:'denied',
      ad_user_data:'denied',
      ad_personalization:'denied'
    });

    window.gtag('config',measurementId,{
      page_location:location.origin+location.pathname
    });

    const script = document.createElement('script');
    script.async = true;
    script.src =
      'https://www.googletagmanager.com/gtag/js?id='+
      encodeURIComponent(measurementId);

    document.head.appendChild(script);
  }

  function refresh() {
    banner.style.display =
      choice === null ? 'block' : 'none';
    settings.style.display =
      choice === null ? 'none' : 'block';
    if (choice === 'yes') loadAnalytics();
  }

  accept.addEventListener('click',function () {
    save('yes');
    refresh();
  });

  decline.addEventListener('click',function () {
    save('no');
    refresh();
  });

  settings.addEventListener('click',function () {
    if (choice === 'yes' && loaded) {
      // Reload after changing consent to stop further GA4 activity.
      save('no');
      location.reload();
      return;
    }
    choice = null;
    refresh();
  });

  refresh();
})();
</script>
<?php endif; ?>
