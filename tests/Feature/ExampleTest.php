<?php

test('the application redirects guests to the panel login page', function () {
    $this->get('/')->assertRedirect('/login');

    $this->get('/login')->assertOk();
});
