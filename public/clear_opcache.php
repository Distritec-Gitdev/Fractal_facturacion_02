<?php

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'Opcache has been reset.';
} else {
    echo 'Opcache is not enabled or opcache_reset() function does not exist.';
} 