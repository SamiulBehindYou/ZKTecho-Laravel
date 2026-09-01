<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Device Reachability Probe
    |--------------------------------------------------------------------------
    |
    | Milliseconds to wait for a device to answer the pre-flight ping before
    | treating it as offline. The underlying ZKTeco library blocks for 60
    | seconds on its own socket read, so this keeps the unattended sync from
    | stalling when a device is powered off or off the network.
    |
    */

    'probe_timeout_ms' => (int) env('ZKTECO_PROBE_TIMEOUT_MS', 2000),

];
