(function (global, factory) {
    if (typeof define === "function" && define.amd) {
        define('ss.AddToCampaignForm', ['jQuery', 'i18n'], factory);
    } else if (typeof exports !== "undefined") {
        factory(require('jQuery'), require('i18n'));
    } else {
        var mod = {
            exports: {}
        };
        factory(global.jQuery, global.i18n);
        global.ssAddToCampaignForm = mod.exports;
    }
})(this, function (_jQuery, _i18n) {
    'use strict';

    var _jQuery2 = _interopRequireDefault(_jQuery);

    var _i18n2 = _interopRequireDefault(_i18n);

    function _interopRequireDefault(obj) {
        return obj && obj.__esModule ? obj : {
            default: obj
        };
    }

    _jQuery2.default.entwine('ss', function ($) {
        $('.ss-addtocampaign').entwine({
            onclick: function onclick() {
                var dialog = $('.ss-addtocampaign-dialog'),
                    url = $('#ss-addtocampaign-url').data('url');

                if (dialog.length) {
                    dialog.open();
                } else {
                    dialog = $('<div class="ss-addtocampaign-dialog loading">');
                    $('body').append(dialog);
                }

                $.ajax({
                    url: url,
                    complete: function complete() {
                        dialog.removeClass('loading');
                    },
                    success: function success(html) {
                        dialog.html(html);
                    }
                });
            }
        }), $('.ss-addtocampaign-dialog').entwine({
            onadd: function onadd() {
                if (!this.is('.ui-dialog-content')) {
                    this.ssdialog({
                        autoOpen: true,
                        minHeight: 200,
                        maxHeight: 200,
                        minWidth: 200,
                        maxWidth: 500
                    });
                }

                this._super();
            },

            open: function open() {
                this.ssdialog('open');
            },

            close: function close() {
                this.ssdialog('close');
            }
        });
    });
});