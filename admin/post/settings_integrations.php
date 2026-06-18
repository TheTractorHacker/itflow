<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Unified integrations page fans out to three existing handler files.
// Only the handler whose action matches the POST key will fire (each exits after responding).
require_once "post/settings_rmm.php";
require_once "post/settings_comet.php";
require_once "post/settings_unifi.php";
