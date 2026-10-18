<?php

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Legacy administrator sign-in has been retired. Use the member portal with an administrator account.',
]);
