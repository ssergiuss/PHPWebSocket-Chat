var Server;
var Authentication = {};

function log(text) {
    var $log = $('#log');

    //Add text to log
    $log.append(($log.val() ? "\n" : '') + text);

    //Autoscroll
    $log[0].scrollTop = $log[0].scrollHeight - $log[0].clientHeight;
}

function send(text) {
    Server.send('message', text);
}

function isAuthenticated() {
    return Authentication.status === 3;
}

function authenticate(message) {
    if (undefined === Authentication.status) {
        Authentication.status = 0;
    }

    if (0 === Authentication.status) {
        readPrivateKeys(function (privateKeys) {
            Authentication.privateKeys = privateKeys;

            readPublicKeys(function (publicKeys) {
                Authentication.username = publicKeys[0];
                Authentication.n = publicKeys[1];
                Authentication.publicKeys = publicKeys.slice(2);

                var n = Authentication.n;

                var r = Math.round(Math.random() * (n - 1));
                // var x = ((Math.round(Math.random() * 1) ? -1 : 1) * r * r) % n;
                var x = (r * r) % n;

                Authentication.r = r;
                Authentication.x = x;

                send(JSON.stringify({
                    username: Authentication.username,
                    x       : Authentication.x
                }));

                Authentication.status = 1;
            });
        });
    } else if (1 === Authentication.status) {
        Authentication.b = JSON.parse(message);

        var n = Authentication.n;
        var b = Authentication.b;
        var s = Authentication.privateKeys;

        var y = Authentication.r;

        for (var i = 0; i < s.length; i++) {
            y = (y * Math.pow(s[i], b[i])) % n;
        }

        send(JSON.stringify({
            y: y
        }));

        Authentication.status = 2;
    } else if (2 === Authentication.status) {
        Authentication.status = 3;
        log('Authenticated.');
    }
}

function readPrivateKeys(callback)
{
    var me = this;
    var fileInput = document.getElementById('private-key-input');

    fileInput.addEventListener('change', function(e) {
        var file = fileInput.files[0];
        var reader = new FileReader();

        reader.onload = function(e) {
            var privateKeys = reader.result.split(' ');
            callback.call(me, privateKeys);
        }

        reader.readAsText(file);
    });
}

function readPublicKeys(callback)
{
    var me = this;
    var fileInput = document.getElementById('public-key-input');

    fileInput.addEventListener('change', function(e) {
        var file = fileInput.files[0];
        var reader = new FileReader();

        reader.onload = function(e) {
            var publicKeys = reader.result.split(' ');
            callback.call(me, publicKeys);
        }

        reader.readAsText(file);
    });
}

$(document).ready(function () {
    log('Connecting...');

    Server = new FancyWebSocket('ws://127.0.0.1:9300');

    $('#message').keypress(function (e) {
        if (isAuthenticated() && e.keyCode == 13 && this.value) {
            log('You: ' + this.value);
            send(this.value);
            $(this).val('');
        }
    });

    Server.bind('open', function () {
        log('Connected.');
        log('Authenticating...');

        authenticate();
    });

    Server.bind('close', function (data) {
        log('Disconnected.');
    });

    Server.bind('message', function(payload) {
        if (!isAuthenticated()) {
            authenticate(payload);
        } else {
            log(payload);
        }
    });

    Server.connect();
});
