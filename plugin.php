<?php
/**
 * Plugin Name: Mini FAIR
 * Description: Create your own FAIR node.
 * Version: 0.1
 * Author: FAIR Contributors
 * License: GPLv2
 * Requires at least: 6.4
 * Requires PHP: 8.3
 * Text Domain: mini-fair
 */

namespace MiniFAIR;

require __DIR__ . '/inc/namespace.php';
require __DIR__ . '/inc/admin/namespace.php';
require __DIR__ . '/inc/api/namespace.php';
require __DIR__ . '/inc/git-updater/namespace.php';
require __DIR__ . '/inc/keys/namespace.php';
require __DIR__ . '/inc/plc/namespace.php';
require __DIR__ . '/inc/plc/util.php';
require __DIR__ . '/vendor/autoload.php';

bootstrap();
