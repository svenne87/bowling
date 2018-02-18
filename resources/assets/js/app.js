
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue');

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

Vue.component('example-component', require('./components/ExampleComponent.vue'));

const app = new Vue({
    el: '#app'
});

// Listen for Pusher Event
Echo.channel('user-rolled')
    .listen('UserRolled', (e) => {
        // Only check this if we are playing on different computers
        if ( $('#match-identifier').length) {
            var matchIdentifier = $('#match-identifier').val();
            var playerIdentifier = $('#player-identifier').val(); // In form

            if (e.matchIdentifier) {
                // Since this is a public channel, listen and see if the information actually concern the current match.
                // No secret information here
                if (e.matchIdentifier == matchIdentifier) {
                     // Just reload for now, when it's this players turn ( The form is not visisble )
                    if (!playerIdentifier) {
                        var player = $('#player').val();

                        if (!e.playerIdentifier) location.reload();

                        if (player == e.playerIdentifier) location.reload();

                        if (e.message) {
                            $('#message').text(e.message);
                        }
                    }

                    if (e.playerName) {
                        // Set next player
                        $('#next-player').text(e.playerName);
                    } else {
                        // Match ended
                        $('#next-player').hide();
                        $('#match-ended').show();
                    }
                }
            } 
        } 
    });