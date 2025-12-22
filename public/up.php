<?php
// Simple health check - bypasses Laravel
http_response_code(200);
header('Content-Type: text/plain');
echo 'OK';

