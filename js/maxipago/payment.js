var maxipago = {
    processing: false,

    identifyCcNumber: function(ccNumber) {
        var creditCard = '';
        var visa = /^4\d{12,15}/;
        var master = /^5[1-5]{1}\d{14}/;
        var amex = /^(34|37)\d{13}/;
        var elo = /^(636368|438935|504175|451416|(6362|5067|4576|4011)\d{2})\d{10}/;
        var discover = /^(6011|622\d{1}|(64|65)\d{2})\d{12}/;
        var hipercard = /^(60\d{2}|3841)\d{9,15}/;
        var diners = /^((30(1|5))|(36|38)\d{1})\d{11}/;
        var jcb = /^(?:2131|1800|35\d{3})\d{11}/;
        var aura = /^50\d{14}/;

        if(visa.test(ccNumber)) {
            creditCard = 'VI';
        } else if(master.test(ccNumber)) {
            creditCard = 'MC';
        } else if(amex.test(ccNumber)) {
            creditCard = 'AM';
        } else if(discover.test(ccNumber)) {
            creditCard = 'DI';
        } else if(diners.test(ccNumber)) {
            creditCard = 'DC';
        } else if (elo.test(ccNumber)) {
            creditCard = 'EL';
        } else if(hipercard.test(ccNumber)) {
            creditCard = 'HC';
        } else if(jcb.test(ccNumber)) {
            creditCard = 'JC';
        } else if(aura.test(ccNumber)) {
            creditCard = 'AU';
        }

        return creditCard;
    },

    validateCreditCard: function(s) {
        // remove non-numerics
        var v = "0123456789";
        var w = "";
        for (i=0; i < s.length; i++) {
            x = s.charAt(i);
            if (v.indexOf(x,0) != -1)
                w += x;
        }
        // validate number
        j = w.length / 2;
        k = Math.floor(j);
        m = Math.ceil(j) - k;
        c = 0;
        for (i=0; i<k; i++) {
            a = w.charAt(i*2+m) * 2;
            c += a > 9 ? Math.floor(a/10 + a%10) : a;
        }
        for (i=0; i<k+m; i++) c += w.charAt(i*2+1-m) * 1;
        return (c%10 == 0);
    },

    removeCard: function(url, customerId, confirmMessage) {
        var self = this;
        if (confim(confirmMessage)) {
            if (!self.processing) {
                var card = $j('select#savedCard option:selected').val();
                if (card != '0') {
                    self.processing = true;
                    $j.ajax({
                        url: url,
                        type: "post",
                        dataType: 'json',
                        data: {
                            'cId': card,
                            'custId': customerId
                        }
                    }).success(function (response) {
                        if (response.code == '200') {
                            $j('select#savedCard option:selected').remove();
                        }
                        self.processing = false;
                    }).error(function(){
                        self.processing = false;
                    });
                }
            }
        }
    }
};

document.observe("dom:loaded", function() {
    if (typeof Review != 'undefined') {
        Review.prototype.nextStep = function (transport) {
            if (transport) {
                var response = transport.responseJSON || transport.responseText.evalJSON(true) || {};

                if (response.redirect) {
                    this.isSuccess = true;
                    if (response.redirect.indexOf('%') >= 0) {
                        location.href = response.redirect;
                    } else {
                        location.href = encodeURI(response.redirect);
                    }
                    return;
                }

                if (response.success) {
                    this.isSuccess = true;
                    location.href = encodeURI(this.successUrl);
                } else {
                    var msg = response.error_messages;
                    if (Object.isArray(msg)) {
                        msg = msg.join("\n").stripTags().toString();
                    }
                    if (msg) {
                        alert(msg);
                    }
                }

                if (response.update_section) {
                    $('checkout-' + response.update_section.name + '-load').update(response.update_section.html);
                }

                if (response.goto_section) {
                    checkout.gotoSection(response.goto_section, true);
                }
            }
        };
    }

});
