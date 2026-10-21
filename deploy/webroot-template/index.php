<?php

// Zion POS - webroot shim (layout B: Laravel app lives outside the web root).
// /home/USER/zion-pos = application; this subdomain folder only bridges it.
require __DIR__ . '/../../zion-pos/public/index.php';
