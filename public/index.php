<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    /*
     * Dev-only CORS safety net for responses that bypass the HttpKernel
     * event cycle.
     *
     * NelmioCorsBundle adds Access-Control-Allow-Origin on kernel.response,
     * so it never runs for hard fatals (out of memory, timeout) or kernel
     * boot failures (e.g. a parse error breaking container compilation).
     * Those error responses then miss the header and the browser hides them
     * from the frontend entirely. This closure runs after Dotenv (APP_ENV is
     * known) but before the kernel boots, so it still covers both cases.
     */
    // Buffer output so that PHP's own fatal-error text (which would otherwise
    // flush HTTP headers at 200 before we can change them) goes into the buffer
    // instead of the wire. On normal exit PHP auto-flushes the buffer; on a
    // fatal we discard it and set 500 ourselves.
    ob_start();
    register_shutdown_function(static function () {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            $output = '';
            while (ob_get_level() > 0) {
                $output = ob_get_clean() . $output;
            }
            http_response_code(500);
            echo $output;
        }
    });

    $corsOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;

    if ('dev' === $context['APP_ENV'] && $corsOrigin) {
        // Answer the CORS preflight before booting the kernel so it still
        // passes when the app cannot boot. Mirrors nelmio_cors.yaml defaults
        // (allow all); the real request is still CORS-checked.
        if ('OPTIONS' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header('Access-Control-Allow-Origin: '.$corsOrigin);
            header('Access-Control-Allow-Methods: GET, OPTIONS, POST, PUT, PATCH, DELETE');
            header('Access-Control-Allow-Headers: '.($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '*'));
            header('Access-Control-Max-Age: 3600');
            header('Vary: Origin');
            http_response_code(204);
            exit;
        }

        // Runs right before headers are flushed, on every response path. Only
        // adds the header when nelmio (or anything else) did not already set it.
        header_register_callback(static function () use ($corsOrigin) {
            foreach (headers_list() as $header) {
                if (0 === stripos($header, 'Access-Control-Allow-Origin:')) {
                    return;
                }
            }
            header('Access-Control-Allow-Origin: '.$corsOrigin, false);
            header('Vary: Origin', false);
        });
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
