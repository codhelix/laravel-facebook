<?php
define ('WITH_REDIRECT_SCRIPT', true);
define ('WITH_REQUEST_PARAMS', true);

Route::get('/channel.html', function(){
	return '<script src="//connect.facebook.net/'. Config::get('laravel-facebook::locale') .'/all.js"></script>';
});
