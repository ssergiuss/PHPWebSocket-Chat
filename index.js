var Server;
var Authenticated = 0;

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
    return Authenticated === 3;
}

function authenticate(message) {
    //Todo...
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
