<?php
require_once 'Backorder_blocker.php';
$variantUpdater = new Backorder_blocker;
$variantUpdater->runDiscontinuedCheck();