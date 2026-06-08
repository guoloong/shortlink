/* global ShortLink */
(function ($) {
    'use strict';

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        // Fallback for older browsers.
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'absolute';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) {
                reject(e);
            } finally {
                document.body.removeChild(ta);
            }
        });
    }

    function flashCopied($btn) {
        var original = $btn.text();
        $btn.addClass('shortlink-copied').text(ShortLink.i18n.copied);
        setTimeout(function () {
            $btn.removeClass('shortlink-copied').text(original);
        }, 1200);
    }

    $(document).on('click', '.sl-copy', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        if (!url) { return; }
        var $btn = $(this);
        copyText(url).then(function () { flashCopied($btn); }).catch(function () { window.prompt(ShortLink.i18n.copyFailed, url); });
    });

    // Regenerate slug button.
    $(document).on('click', '.sl-regen', function (e) {
        e.preventDefault();
        var $input = $('#sl-custom-slug');
        if ($input.length) {
            $input.val('').attr('placeholder', '...');
        }
    });

    // QR link in list table.
    $(document).on('click', '.sl-qr-link', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        if (!url) { return; }
        var win = window.open('', '_blank', 'width=320,height=360');
        if (!win) { return; }
        win.document.write('<!doctype html><html><head><title>' + url + '</title><style>body{font-family:system-ui,sans-serif;text-align:center;margin:24px}h1{font-size:14px;word-break:break-all}img{margin-top:12px}</style></head><body><h1>' + url + '</h1><div id="q">Loading…</div></body></html>');
        $.get(ShortLink.ajaxUrl, { action: 'shortlink_qr', nonce: ShortLink.nonce, url: url }, function (resp) {
            if (resp && resp.success && resp.data && resp.data.img) {
                win.document.getElementById('q').innerHTML = resp.data.img;
            } else {
                win.document.getElementById('q').textContent = ShortLink.i18n.error;
            }
        });
    });

    // Download QR.
    $(document).on('click', '#sl-download-qr', function (e) {
        e.preventDefault();
        var $img = $('.sl-qr .sl-qr-img');
        if (!$img.length) { return; }
        var src = $img.attr('src') || '';
        if (!src) { return; }
        // Open in new tab; right-click "Save As" works there.
        // Or force a download by fetching and saving as blob.
        fetch(src, { mode: 'cors' }).then(function (r) { return r.blob(); }).then(function (blob) {
            var blobUrl = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = blobUrl;
            a.download = 'shortlink-qr.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(blobUrl);
        }).catch(function () {
            window.open(src, '_blank');
        });
    });
})(jQuery);
