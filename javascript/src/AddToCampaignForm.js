import $ from 'jQuery';
import i18n from 'i18n';

$.entwine('ss', function($){
    $('.ss-addtocampaign').entwine({
        onclick: function() {
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
                complete: function() {
                    dialog.removeClass('loading');
                },
                success: function(html) {
                    dialog.html(html);
                }
            });
        }
    }),

    $('.ss-addtocampaign-dialog').entwine({
        onadd: function() {
            // Create jQuery dialog
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

        open: function() {
            this.ssdialog('open');
        },

        close: function() {
            this.ssdialog('close');
        }
    });
})